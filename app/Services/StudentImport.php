<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads a student list from Excel or CSV (an SDMS export or the school's own file),
 * matches its columns to student fields, checks every row, then registers the valid ones.
 */
class StudentImport
{
    public const MAX_ROWS = 5000;

    /** Field => [label, header names it is recognised by (lower case, letters and digits only)]. */
    public const FIELDS = [
        'sdms_id' => ['SDMS ID', ['sdmsid', 'sdms', 'sdmscode', 'studentid', 'learnerid', 'studentcode', 'learnercode', 'uniqueid']],
        'reg_number' => ['Registration number', ['regnumber', 'registrationnumber', 'regno', 'registrationno', 'matricule', 'admissionnumber', 'admno', 'admissionno']],
        'full_name' => ['Full name (both names in one column)', ['names', 'fullname', 'name', 'studentname', 'studentnames', 'learnername', 'learnernames', 'amazina', 'noms', 'nomscomplets']],
        'last_name' => ['Last name', ['lastname', 'surname', 'familyname', 'nom', 'izinaryumuryango', 'nomdefamille']],
        'first_name' => ['First name', ['firstname', 'givenname', 'givennames', 'othernames', 'prenom', 'prenoms', 'izinabwite']],
        'gender' => ['Gender', ['gender', 'sex', 'sexe', 'igitsina', 'gendermf', 'sexmf']],
        'date_of_birth' => ['Date of birth', ['dateofbirth', 'dob', 'birthdate', 'datedenaissance', 'itarikiyamavuko', 'dateofbirthddmmyyyy', 'born']],
        'national_id' => ['National ID', ['nationalid', 'nid', 'idnumber', 'indangamuntu']],
        'class' => ['Class', ['class', 'classe', 'ishuri', 'classname', 'classgroup', 'grade']],
        'guardian_name' => ['Parent or guardian name', ['parent', 'guardian', 'parentname', 'guardianname', 'parentnames', 'umubyeyi', 'parentguardian', 'parentorguardian', 'parentorguardianname']],
        'guardian_phone' => ['Parent phone', ['phone', 'parentphone', 'guardianphone', 'telephone', 'tel', 'contact', 'mobile', 'telefoni', 'phonenumber', 'parentcontact']],
        'guardian_relationship' => ['Relationship', ['relationship', 'relation', 'relationshipfathermotherguardian']],
        'district' => ['District', ['district', 'akarere']],
        'sector' => ['Sector', ['sector', 'umurenge', 'secteur']],
        'special_needs' => ['Special needs', ['specialneeds', 'disability', 'needs', 'learningneeds']],
        'admission_date' => ['Admission date', ['admissiondate', 'dateofadmission', 'datejoined', 'joined']],
    ];

    public function __construct(private StudentRegistrar $registrar) {}

    /** @return array{headers: string[], rows: array<int, array<int, mixed>>, header_row: int} */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);
        $all = $sheet->toArray(null, true, false, false);

        // The header row is the first of the top 10 rows where at least two cells are known column names
        // (exports often start with a title row).
        $headerRow = 0;
        foreach (array_slice($all, 0, 10) as $i => $row) {
            $known = collect($row)->filter(fn ($cell) => $this->fieldFor((string) $cell) !== null)->count();
            if ($known >= 2) {
                $headerRow = $i;
                break;
            }
        }

        $headers = array_map(fn ($h) => trim((string) $h), $all[$headerRow] ?? []);
        while ($headers && end($headers) === '') {
            array_pop($headers);
        }
        $rows = collect(array_slice($all, $headerRow + 1))
            ->map(fn ($row) => array_slice($row, 0, count($headers)))
            ->filter(fn ($row) => collect($row)->contains(fn ($v) => trim((string) $v) !== ''))
            ->values()->all();

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('The file has more than :n students. Split it into several files.', ['n' => self::MAX_ROWS])]);
        }

        return ['headers' => $headers, 'rows' => $rows, 'header_row' => $headerRow + 1];
    }

    /** @return array<int, ?string> column index => field */
    public function guessMapping(array $headers): array
    {
        $used = [];
        $mapping = [];
        foreach ($headers as $i => $header) {
            $field = $this->fieldFor($header);
            $mapping[$i] = $field && ! in_array($field, $used, true) ? $field : null;
            if ($mapping[$i]) {
                $used[] = $mapping[$i];
            }
        }

        return $mapping;
    }

    /**
     * Checks every row. Returns the cleaned rows and a report of problems; nothing is saved.
     *
     * @param  array  $options  {class_id?: int, enrollment_type: string, update_existing: bool}
     */
    public function check(array $rows, array $mapping, array $options): array
    {
        $year = AcademicYear::current();
        $classes = $year ? SchoolClass::query()->where('academic_year_id', $year->id)->get(['id', 'name'])
            ->mapWithKeys(fn ($c) => [$this->classKey($c->name) => $c]) : collect();
        $fixedClass = ! empty($options['class_id']) ? SchoolClass::query()->find($options['class_id']) : null;

        $mapped = array_filter($mapping);
        if (! in_array('full_name', $mapped, true) && ! (in_array('first_name', $mapped, true) && in_array('last_name', $mapped, true))) {
            throw ValidationException::withMessages(['mapping' => __('Choose the column with the names: either "Full name", or both "First name" and "Last name".')]);
        }
        if (! in_array('gender', $mapped, true)) {
            throw ValidationException::withMessages(['mapping' => __('Choose the column with the gender.')]);
        }

        $existingSdms = Student::query()->whereNotNull('sdms_id')->pluck('id', 'sdms_id')->mapWithKeys(fn ($id, $k) => [strtoupper($k) => $id]);
        $existingReg = Student::query()->whereNotNull('reg_number')->pluck('id', 'reg_number')->mapWithKeys(fn ($id, $k) => [strtoupper($k) => $id]);
        $seen = [];
        $out = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 1;
            $v = [];
            foreach ($mapping as $col => $field) {
                if ($field) {
                    $v[$field] = $raw[$col] ?? null;
                }
            }
            $errors = [];
            $text = fn ($key) => ($s = trim(preg_replace('/\s+/', ' ', (string) ($v[$key] ?? '')))) === '' ? null : $s;

            [$first, $last] = [$text('first_name'), $text('last_name')];
            if ((! $first || ! $last) && $text('full_name')) {
                [$first, $last] = $this->splitName($text('full_name'));
            }
            if (! $first || ! $last) {
                $errors[] = __('Both names are needed.');
            }

            $gender = $this->gender($text('gender'));
            if (! $gender) {
                $errors[] = __('Gender ":value" is not M or F.', ['value' => $text('gender') ?? '']);
            }

            $dob = $this->date($v['date_of_birth'] ?? null);
            if ($text('date_of_birth') && ! $dob) {
                $errors[] = __('Date of birth ":value" is not a date (use DD/MM/YYYY).', ['value' => $text('date_of_birth')]);
            }
            $admission = $this->date($v['admission_date'] ?? null);

            $sdms = $text('sdms_id') ? strtoupper($text('sdms_id')) : null;
            $reg = $text('reg_number');
            foreach (['sdms' => $sdms, 'reg' => $reg ? strtoupper($reg) : null] as $kind => $key) {
                if ($key && isset($seen[$kind][$key])) {
                    $errors[] = __('Same :what as row :row.', ['what' => $kind === 'sdms' ? 'SDMS ID' : __('registration number'), 'row' => $seen[$kind][$key]]);
                }
                if ($key) {
                    $seen[$kind][$key] = $line;
                }
            }
            $existingId = ($sdms ? $existingSdms[$sdms] ?? null : null) ?? ($reg ? $existingReg[strtoupper($reg)] ?? null : null);

            $class = $fixedClass;
            if (! $class && $text('class')) {
                $class = $classes[$this->classKey($text('class'))] ?? null;
                if (! $class) {
                    $errors[] = __('Class ":value" does not exist this year.', ['value' => $text('class')]);
                }
            }

            $guardian = null;
            $phone = $text('guardian_phone') ? Phone::normalize($text('guardian_phone')) : null;
            if ($phone && ! Phone::isValid($phone)) {
                $errors[] = __('Phone ":value" is not a Rwandan mobile number.', ['value' => $text('guardian_phone')]);
            }
            if ($text('guardian_name') || $phone) {
                if (! $text('guardian_name')) {
                    $errors[] = __('The parent phone has no parent name.');
                } elseif (! $phone) {
                    $errors[] = __('The parent has no phone number.');
                }
                [$gFirst, $gLast] = $this->splitName($text('guardian_name') ?? '');
                $guardian = ['first_name' => $gFirst, 'last_name' => $gLast, 'phone' => $phone ? Phone::local($phone) : null,
                    'relationship' => $this->relationship($text('guardian_relationship')), 'is_primary' => true];
            }

            $out[] = [
                'line' => $line,
                'errors' => $errors,
                'existing_id' => $existingId,
                'action' => $errors ? 'error' : ($existingId ? ($options['update_existing'] ? 'update' : 'skip') : 'create'),
                'student' => [
                    'sdms_id' => $sdms, 'reg_number' => $reg, 'first_name' => $first, 'last_name' => $last, 'gender' => $gender,
                    'date_of_birth' => $dob, 'national_id' => $text('national_id'), 'district' => $text('district'),
                    'sector' => $text('sector'), 'special_needs' => $text('special_needs'), 'admission_date' => $admission,
                ],
                'guardian' => $guardian,
                'class_id' => $class?->id,
                'class' => $class?->name,
            ];
        }

        return $out;
    }

    /** Saves the rows that passed the check. Each student is saved on its own, so one failure does not stop the rest. */
    public function run(array $checked, array $options): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => []];
        foreach ($checked as $row) {
            if (in_array($row['action'], ['error', 'skip'], true)) {
                $result['skipped']++;

                continue;
            }
            try {
                DB::transaction(function () use ($row, $options, &$result) {
                    $existing = $row['existing_id'] ? Student::query()->find($row['existing_id']) : null;
                    $fields = $existing ? array_filter($row['student'], fn ($v) => $v !== null) : $row['student'];
                    $enrollment = $row['class_id'] ? ['class_id' => $row['class_id'], 'enrollment_type' => $options['enrollment_type'] ?? 'new',
                        'date_joined' => $row['student']['admission_date']] : null;

                    // Keep guardians already linked; add the one from the file.
                    $guardians = $existing ? $existing->guardians->map(fn (Guardian $g) => [
                        'id' => $g->id, 'first_name' => $g->first_name, 'last_name' => $g->last_name, 'phone' => Phone::local($g->phone),
                        'relationship' => $g->pivot->relationship, 'is_primary' => (bool) $g->pivot->is_primary,
                    ])->all() : [];
                    if ($row['guardian'] && ! collect($guardians)->contains(fn ($g) => $g['phone'] === $row['guardian']['phone'])) {
                        $guardians[] = [...$row['guardian'], 'is_primary' => ! $guardians];
                    }

                    $this->registrar->save($existing, $fields, $guardians, $enrollment);
                    $result[$existing ? 'updated' : 'created']++;
                });
            } catch (\Throwable $e) {
                $message = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : __('Could not be saved.');
                $result['failed'][] = ['line' => $row['line'], 'name' => trim($row['student']['first_name'].' '.$row['student']['last_name']), 'error' => $message];
                if (! $e instanceof ValidationException) {
                    report($e);
                }
            }
        }
        AuditLog::record('import', 'students', null, collect($result)->except('failed')->all() + ['failed' => count($result['failed'])]);

        return $result;
    }

    // ----------------------------------------------------------------------------------------------

    private function fieldFor(string $header): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($header)));
        if ($key === '') {
            return null;
        }
        foreach (self::FIELDS as $field => [, $aliases]) {
            if (in_array($key, $aliases, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * "UWIMANA Jean Claude" => first "Jean Claude", last "UWIMANA" (family names are often in capitals).
     * Without capitals, the first word is taken as the last name, as in most school lists.
     */
    private function splitName(string $name): array
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) < 2) {
            return [$words[0] ?? null, null];
        }
        $caps = array_filter($words, fn ($w) => mb_strlen($w) > 1 && mb_strtoupper($w) === $w && preg_match('/\p{L}/u', $w));
        if ($caps && count($caps) < count($words)) {
            $last = implode(' ', $caps);
            $first = implode(' ', array_diff_key($words, $caps));

            return [$first, $last];
        }

        return [implode(' ', array_slice($words, 1)), $words[0]];
    }

    private function gender(?string $value): ?string
    {
        $v = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii((string) $value)));

        return match (true) {
            in_array($v, ['m', 'male', 'boy', 'gabo', 'masculin', 'h', 'homme', 'garcon', 'umuhungu'], true) => 'M',
            in_array($v, ['f', 'female', 'girl', 'gore', 'feminin', 'femme', 'fille', 'umukobwa'], true) => 'F',
            default => null,
        };
    }

    private function relationship(?string $value): string
    {
        $v = Str::lower(Str::ascii((string) $value));

        return match (true) {
            Str::contains($v, ['father', 'papa', 'pere', 'se ']) || $v === 'se' => 'father',
            Str::contains($v, ['mother', 'mama', 'mere', 'nyina']) => 'mother',
            default => 'guardian',
        };
    }

    /** Excel day numbers, DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY or YYYY-MM-DD. */
    private function date(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value) && $value > 2000 && $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }
        $value = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?$/', '', trim((string) $value));
        if (preg_match('/^(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2}|\d{4})$/', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                $y += $y <= (int) now()->format('y') ? 2000 : 1900;
            }
        } else {
            return null;
        }

        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** "s1 a", "S1A" and "S1  A" all match class "S1 A". */
    private function classKey(string $name): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($name));
    }
}
