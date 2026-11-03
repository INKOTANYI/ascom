<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Services\StudentImport;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Student lists in and out of Excel: import (FR-04), the template, and class lists. */
class StudentImportController extends Controller
{
    private const FOLDER = 'imports';

    /** Columns of the template and of exported lists, so an exported list can be imported again. */
    private const COLUMNS = [
        'SDMS ID', 'Reg number', 'Last name', 'First name', 'Gender', 'Date of birth', 'Class',
        'Parent name', 'Parent phone', 'Relationship', 'District', 'Sector', 'National ID', 'Special needs',
    ];

    public function __construct(private StudentImport $import) {}

    public function show(Request $request): Response
    {
        $token = $request->string('token')->toString();
        $file = $token ? $this->file($token) : null;
        $sheet = null;
        if ($file) {
            $data = $this->import->read(Storage::disk('local')->path($file));
            $sheet = [
                'token' => $token,
                'name' => $request->session()->get("import.{$token}.name", basename($file)),
                'headers' => $data['headers'],
                'header_row' => $data['header_row'],
                'count' => count($data['rows']),
                'sample' => array_slice($data['rows'], 0, 5),
                'mapping' => $request->session()->get("import.{$token}.mapping") ?? $this->import->guessMapping($data['headers']),
            ];
        }
        $year = AcademicYear::current();

        return Inertia::render('Students/Import', [
            'sheet' => $sheet,
            'report' => $request->session()->get('importReport'),
            'fields' => collect(StudentImport::FIELDS)->map(fn ($f, $k) => ['value' => $k, 'label' => __($f[0])])->values(),
            'classes' => $year ? SchoolClass::query()->where('academic_year_id', $year->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]) : [],
            'enrollmentTypes' => collect(Enrollment::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => __($l)])->values(),
            'year' => $year?->only(['id', 'name']),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt,ods']]);
        $this->cleanOld();
        $token = (string) Str::uuid();
        $file = $request->file('file');
        $file->storeAs(self::FOLDER, $token.'.'.strtolower($file->getClientOriginalExtension() ?: 'xlsx'), 'local');
        $request->session()->put("import.{$token}.name", $file->getClientOriginalName());

        try {
            $this->import->read(Storage::disk('local')->path($this->file($token)));
        } catch (\PhpOffice\PhpSpreadsheet\Exception|\ValueError) {
            return back()->withErrors(['file' => __('This file cannot be read. Save it as Excel (.xlsx) or CSV and try again.')]);
        }

        return redirect()->route('students.import', ['token' => $token]);
    }

    /** Checks the rows (action "check") or registers the valid ones (action "import"). */
    public function run(Request $request): RedirectResponse
    {
        $year = AcademicYear::current();
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'action' => ['required', Rule::in(['check', 'import'])],
            'mapping' => ['array'],
            'mapping.*' => ['nullable', Rule::in(array_keys(StudentImport::FIELDS))],
            'class_id' => ['nullable', Rule::exists('classes', 'id')->where('academic_year_id', $year?->id)],
            'enrollment_type' => ['required', Rule::in(array_keys(Enrollment::TYPES))],
            'update_existing' => ['boolean'],
        ]);
        $file = $this->file($data['token']);
        abort_unless($file, 404);

        $mapping = array_map(fn ($v) => $v ?: null, $data['mapping'] ?? []);
        $duplicates = collect($mapping)->filter()->duplicates();
        if ($duplicates->isNotEmpty()) {
            return back()->withErrors(['mapping' => __('Each field can be chosen for one column only.')]);
        }
        $request->session()->put("import.{$data['token']}.mapping", $mapping);

        $options = ['class_id' => $data['class_id'] ?? null, 'enrollment_type' => $data['enrollment_type'], 'update_existing' => (bool) ($data['update_existing'] ?? false)];
        $rows = $this->import->read(Storage::disk('local')->path($file))['rows'];
        $checked = $this->import->check($rows, $mapping, $options);

        $summary = collect($checked)->countBy('action')->all();
        $report = [
            'action' => $data['action'],
            'total' => count($checked),
            'create' => $summary['create'] ?? 0,
            'update' => $summary['update'] ?? 0,
            'skip' => $summary['skip'] ?? 0,
            'errors' => collect($checked)->where('action', 'error')->take(200)
                ->map(fn ($r) => ['line' => $r['line'], 'name' => trim(($r['student']['first_name'] ?? '').' '.($r['student']['last_name'] ?? '')), 'messages' => $r['errors']])->values(),
            'error_count' => $summary['error'] ?? 0,
            'preview' => collect($checked)->where('action', '!=', 'error')->take(8)->map(fn ($r) => [
                'line' => $r['line'], 'action' => $r['action'], 'name' => $r['student']['last_name'].' '.$r['student']['first_name'],
                'gender' => $r['student']['gender'], 'sdms_id' => $r['student']['sdms_id'], 'date_of_birth' => $r['student']['date_of_birth'],
                'class' => $r['class'], 'guardian' => $r['guardian'] ? trim($r['guardian']['first_name'].' '.$r['guardian']['last_name']) : null,
                'phone' => $r['guardian']['phone'] ?? null ? Phone::display('+250'.$r['guardian']['phone']) : null,
            ])->values(),
        ];

        if ($data['action'] === 'import') {
            $report['result'] = $this->import->run($checked, $options);
            Storage::disk('local')->delete($file);
            $request->session()->forget("import.{$data['token']}");

            return redirect()->route('students.import')->with('importReport', $report)
                ->with('success', __(':n students registered.', ['n' => $report['result']['created'] + $report['result']['updated']]));
        }

        return back()->with('importReport', $report);
    }

    /** An Excel file with the expected columns, two example rows and instructions. */
    public function template(): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(__('Students'));
        $sheet->fromArray(array_map(fn ($c) => __($c), self::COLUMNS));
        $sheet->fromArray([
            ['', '', 'UWIMANA', 'Aline', 'F', '14/03/2013', 'S1 A', 'MUKAMANA Josiane', '0788123456', 'mother', 'Kamonyi', 'Runda', '', ''],
            ['', '', 'NSHIMIYIMANA', 'Eric', 'M', '02/11/2012', 'S1 A', 'HABIMANA Jean', '0722123456', 'father', 'Kamonyi', 'Rugalika', '', ''],
        ], null, 'A2');
        $this->styleHeader($sheet, count(self::COLUMNS));
        $sheet->getStyle('A2:N3')->getFont()->getColor()->setRGB('64748B');

        $help = $book->createSheet()->setTitle(__('How to fill'));
        $help->fromArray(array_map(fn ($l) => [$l], [
            __('One student per row. Delete the two example rows before importing.'),
            __('Last name and First name are required. Gender: M or F.'),
            __('Dates as DD/MM/YYYY, for example 14/03/2013.'),
            __('Class must be written as in Smart School, for example S1 A or S4 MPC A. You can also choose one class for the whole file when importing.'),
            __('Parent phone: a Rwandan mobile number, for example 0788123456. This parent receives SMS.'),
            __('Relationship: father, mother or guardian.'),
            __('SDMS ID: copy it from SDMS. Students already registered with the same SDMS ID are not added twice.'),
        ]));
        $help->getColumnDimension('A')->setWidth(120);

        return $this->download($book, 'students-template.xlsx');
    }

    /** The filtered student list as Excel, in the same columns as the template. */
    public function export(Request $request): StreamedResponse
    {
        $year = AcademicYear::current();
        $classId = $request->integer('class_id') ?: null;
        $status = $request->input('status', 'active');

        $students = Student::query()
            ->search($request->string('search')->toString())
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(in_array($request->input('gender'), ['M', 'F'], true), fn ($q) => $q->where('gender', $request->input('gender')))
            ->when($classId > 0, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('class_id', $classId)))
            ->when($classId === -1 && $year, fn ($q) => $q->whereDoesntHave('enrollments', fn ($e) => $e->where('academic_year_id', $year->id)))
            ->with(['currentEnrollment.schoolClass:id,name', 'primaryGuardian'])
            ->get()
            ->sortBy(fn (Student $s) => [$s->currentEnrollment?->schoolClass?->name ?? 'ZZZ', $s->last_name, $s->first_name]);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(__('Students'));
        $sheet->fromArray(array_map(fn ($c) => __($c), self::COLUMNS));
        $sheet->fromArray($students->map(function (Student $s) {
            $g = $s->primaryGuardian->first();

            return [
                $s->sdms_id, $s->reg_number, $s->last_name, $s->first_name, $s->gender, $s->date_of_birth?->format('d/m/Y'),
                $s->currentEnrollment?->schoolClass?->name, $g?->full_name, $g?->phone ? '0'.Phone::local($g->phone) : null,
                $g?->pivot->relationship, $s->district, $s->sector, $s->national_id, $s->special_needs,
            ];
        })->values()->all(), null, 'A2', true);
        $this->styleHeader($sheet, count(self::COLUMNS));
        // Keep IDs and phones as text so Excel does not drop leading zeros.
        $last = $students->count() + 1;
        foreach (['A', 'B', 'I', 'M'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$last}")->getNumberFormat()->setFormatCode('@');
        }

        $school = Str::slug(SchoolProfile::current()?->short_name ?? 'school');
        $class = $classId > 0 ? '-'.Str::slug(SchoolClass::query()->find($classId)?->name ?? '') : '';

        return $this->download($book, "{$school}-students{$class}-".now()->format('Y-m-d').'.xlsx');
    }

    // ----------------------------------------------------------------------------------------------

    private function styleHeader($sheet, int $columns): void
    {
        $lastCol = chr(ord('A') + $columns - 1);
        $style = $sheet->getStyle("A1:{$lastCol}1");
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0E7C4A');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    private function download(Spreadsheet $book, string $name): StreamedResponse
    {
        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function file(string $token): ?string
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        return collect(Storage::disk('local')->files(self::FOLDER))->first(fn ($f) => Str::startsWith(basename($f), $token.'.'));
    }

    /** Uploaded files are only needed for a few minutes. */
    private function cleanOld(): void
    {
        foreach (Storage::disk('local')->files(self::FOLDER) as $f) {
            if (Storage::disk('local')->lastModified($f) < now()->subDay()->getTimestamp()) {
                Storage::disk('local')->delete($f);
            }
        }
    }
}
