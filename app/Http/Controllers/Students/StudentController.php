<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentRegistrar;
use App\Support\Phone;
use App\Support\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Student records, guardians and class placement (FR-04, FR-05). */
class StudentController extends Controller
{
    public function __construct(private StudentRegistrar $registrar) {}

    public function index(Request $request): Response
    {
        $year = AcademicYear::current();
        $filters = [
            'search' => $request->string('search')->toString(),
            'class_id' => $request->integer('class_id') ?: null,
            'status' => $request->input('status', 'active'),
            'gender' => in_array($request->input('gender'), ['M', 'F'], true) ? $request->input('gender') : null,
        ];

        $students = $this->filtered($filters, $year)
            ->with(['currentEnrollment.schoolClass:id,name', 'primaryGuardian:id,first_name,last_name,phone'])
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25)->withQueryString()
            ->through(fn (Student $s) => $this->row($s));

        $active = Student::query()->where('status', 'active');

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $filters,
            'classes' => $this->classOptions($year),
            'statuses' => $this->statusOptions(),
            'summary' => [
                'active' => (clone $active)->count(),
                'boys' => (clone $active)->where('gender', 'M')->count(),
                'girls' => (clone $active)->where('gender', 'F')->count(),
                'no_class' => $year ? (clone $active)->whereDoesntHave('enrollments', fn ($q) => $q->where('academic_year_id', $year->id))->count() : 0,
            ],
            'year' => $year?->only(['id', 'name']),
            'canManage' => $request->user()->can('students.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $request->integer('class_id') ?: null);
    }

    public function store(Request $request): RedirectResponse
    {
        [$student, $guardians, $enrollment] = $this->validated($request);
        $record = $this->registrar->save(null, $student, $guardians, $enrollment);
        $this->savePhoto($request, $record);

        return $request->boolean('add_another')
            ? redirect()->route('students.create', ['class_id' => $enrollment['class_id'] ?? null])->with('success', __(':name is registered. Add the next student.', ['name' => $record->full_name]))
            : redirect()->route('students.show', $record)->with('success', __(':name is registered.', ['name' => $record->full_name]));
    }

    public function show(Request $request, Student $student): Response
    {
        $student->load([
            'guardians' => fn ($q) => $q->withCount('students'),
            'enrollments' => fn ($q) => $q->with(['schoolClass:id,name', 'academicYear:id,name,is_current'])->orderByDesc('academic_year_id'),
            'transfers.recorder:id,name',
        ]);

        $history = $request->user()->can('audit.view')
            ? AuditLog::query()->with('user:id,name')
                ->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('table_name', ['students', 'student_guardians'])->where('record_id', $student->id))
                    ->orWhere(fn ($q) => $q->where('table_name', 'enrollments')->whereIn('record_id', $student->enrollments->pluck('id'))))
                ->latest('id')->limit(30)->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id, 'action' => $log->action, 'table' => $log->table_name, 'user' => $log->user?->name,
                    'at' => $log->created_at->toIso8601String(), 'old' => $log->old_values, 'new' => $log->new_values,
                ])
            : null;

        $year = AcademicYear::current();

        return Inertia::render('Students/Show', [
            'student' => [
                ...$this->row($student),
                ...$student->only(['national_id', 'district', 'sector', 'special_needs', 'admission_date', 'date_of_birth']),
                'admission_date' => $student->admission_date?->toDateString(),
                'date_of_birth' => $student->date_of_birth?->toDateString(),
            ],
            'guardians' => $student->guardians->map(fn (Guardian $g) => [
                'id' => $g->id, 'full_name' => $g->full_name, 'phone_display' => Phone::display($g->phone), 'email' => $g->email,
                'occupation' => $g->occupation, 'national_id' => $g->national_id,
                'relationship' => $g->pivot->relationship, 'relationship_label' => __(Guardian::RELATIONSHIPS[$g->pivot->relationship] ?? $g->pivot->relationship),
                'is_primary' => (bool) $g->pivot->is_primary, 'children' => $g->students_count,
            ]),
            'enrollments' => $student->enrollments->map(fn (Enrollment $e) => [
                'id' => $e->id, 'year' => $e->academicYear->name, 'is_current' => $e->academicYear->is_current,
                'class' => $e->schoolClass->name, 'class_id' => $e->class_id,
                'type' => __(Enrollment::TYPES[$e->enrollment_type] ?? $e->enrollment_type), 'status' => $e->status,
                'date_joined' => $e->date_joined?->toDateString(), 'date_left' => $e->date_left?->toDateString(),
            ]),
            'transfers' => $student->transfers->map(fn ($t) => [
                'id' => $t->id, 'direction' => $t->direction, 'other_school' => $t->other_school, 'other_school_code' => $t->other_school_code,
                'date' => $t->transfer_date->toDateString(), 'reason' => $t->reason, 'by' => $t->recorder?->name,
            ]),
            'history' => $history,
            'classes' => $this->classOptions($year),
            'statuses' => $this->statusOptions(),
            'year' => $year?->only(['id', 'name']),
            'canManage' => $request->user()->can('students.manage'),
        ]);
    }

    public function edit(Student $student): Response
    {
        return $this->form($student, null);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        [$data, $guardians, $enrollment] = $this->validated($request, $student);
        $this->registrar->save($student, $data, $guardians, $enrollment);
        $this->savePhoto($request, $student);

        return redirect()->route('students.show', $student)->with('success', __('Saved.'));
    }

    /** Moves the student to another class of the current year. */
    public function changeClass(Request $request, Student $student): RedirectResponse
    {
        $year = AcademicYear::current();
        $data = $request->validate([
            'class_id' => ['required', Rule::exists('classes', 'id')->where('academic_year_id', $year?->id)],
            'enrollment_type' => ['nullable', Rule::in(array_keys(Enrollment::TYPES))],
        ]);
        $enrollment = $this->registrar->enrol($student, $data);

        return back()->with('success', __(':name is now in :class.', ['name' => $student->full_name, 'class' => $enrollment->schoolClass->name]));
    }

    /** Active, suspended, transferred out, dropped out or graduated (BR-11). */
    public function changeStatus(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Student::STATUSES))],
            'date' => ['required', 'date'],
            'other_school' => ['nullable', 'required_if:status,transferred', 'string', 'max:200'],
            'other_school_code' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($data['status'] === $student->status) {
            throw ValidationException::withMessages(['status' => __('The student already has this status.')]);
        }
        $this->registrar->changeStatus($student, $data['status'], $data);

        return back()->with('success', __('Status changed to :status.', ['status' => __(Student::STATUSES[$data['status']])]));
    }

    public function photo(Student $student): StreamedResponse
    {
        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Existing guardians with this phone, so brothers and sisters share one parent record. */
    public function guardianLookup(Request $request)
    {
        $phone = Phone::normalize($request->string('phone')->toString());
        if (! Phone::isValid($phone)) {
            return response()->json([]);
        }

        return Guardian::query()->where('phone', $phone)->with('students:id,first_name,last_name')->limit(5)->get()
            ->map(fn (Guardian $g) => [
                'id' => $g->id, 'first_name' => $g->first_name, 'last_name' => $g->last_name, 'email' => $g->email,
                'national_id' => $g->national_id, 'occupation' => $g->occupation,
                'children' => $g->students->map->full_name->values(),
            ]);
    }

    // ----------------------------------------------------------------------------------------------

    private function form(?Student $student, ?int $classId): Response
    {
        $year = AcademicYear::current();
        $student?->load(['guardians', 'currentEnrollment']);

        return Inertia::render('Students/Form', [
            'student' => $student ? [
                ...$student->only(['id', 'sdms_id', 'reg_number', 'first_name', 'last_name', 'gender', 'national_id', 'district', 'sector', 'special_needs', 'status']),
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'admission_date' => $student->admission_date?->toDateString(),
                'photo_url' => $student->photo_path ? route('students.photo', [$student, 'v' => substr(md5($student->photo_path), 0, 6)]) : null,
                'class_id' => $student->currentEnrollment?->class_id,
                'enrollment_type' => $student->currentEnrollment?->enrollment_type,
                'guardians' => $student->guardians->map(fn (Guardian $g) => [
                    'id' => $g->id, 'first_name' => $g->first_name, 'last_name' => $g->last_name ?? '', 'phone' => Phone::local($g->phone),
                    'email' => $g->email ?? '', 'national_id' => $g->national_id ?? '', 'occupation' => $g->occupation ?? '',
                    'relationship' => $g->pivot->relationship, 'is_primary' => (bool) $g->pivot->is_primary,
                ])->values(),
            ] : null,
            'defaultClassId' => $classId,
            'classes' => $this->classOptions($year),
            'year' => $year?->only(['id', 'name']),
            'enrollmentTypes' => collect(Enrollment::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => __($l)])->values(),
            'relationships' => collect(Guardian::RELATIONSHIPS)->map(fn ($l, $v) => ['value' => $v, 'label' => __($l)])->values(),
        ]);
    }

    /** @return array{0: array, 1: array, 2: ?array} */
    private function validated(Request $request, ?Student $student = null): array
    {
        $year = AcademicYear::current();
        $data = $request->validate([
            'sdms_id' => ['nullable', 'string', 'max:30', Rule::unique('students', 'sdms_id')->ignore($student?->id)],
            'reg_number' => ['nullable', 'string', 'max:30', Rule::unique('students', 'reg_number')->ignore($student?->id)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1980-01-01'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'district' => ['nullable', 'string', 'max:50'],
            'sector' => ['nullable', 'string', 'max:50'],
            'special_needs' => ['nullable', 'string', 'max:2000'],
            'admission_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'remove_photo' => ['boolean'],
            'class_id' => ['nullable', Rule::exists('classes', 'id')->where('academic_year_id', $year?->id)],
            'enrollment_type' => ['nullable', Rule::in(array_keys(Enrollment::TYPES))],
            'previous_school' => ['nullable', 'string', 'max:200'],
            'previous_school_code' => ['nullable', 'string', 'max:20'],
            'guardians' => ['array', 'max:3'],
            'guardians.*.id' => ['nullable', 'integer', 'exists:guardians,id'],
            'guardians.*.first_name' => ['required', 'string', 'max:80'],
            'guardians.*.last_name' => ['nullable', 'string', 'max:80'],
            'guardians.*.phone' => ['nullable', 'regex:/^7[2389]\d{7}$/'],
            'guardians.*.email' => ['nullable', 'email', 'max:120'],
            'guardians.*.national_id' => ['nullable', 'string', 'max:20'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:80'],
            'guardians.*.relationship' => ['required', Rule::in(array_keys(Guardian::RELATIONSHIPS))],
            'guardians.*.is_primary' => ['boolean'],
        ], [
            'guardians.*.phone.regex' => __('Enter the 9 digits after +250, starting with 72, 73, 78 or 79.'),
            'sdms_id.unique' => __('Another student already has this SDMS ID.'),
            'reg_number.unique' => __('Another student already has this registration number.'),
        ]);

        $guardians = $data['guardians'] ?? [];
        // The primary guardian receives the SMS, so they need a phone number (BR-10).
        $primary = collect($guardians)->search(fn ($g) => ! empty($g['is_primary']));
        $primary = $primary === false ? 0 : $primary;
        if ($guardians && empty($guardians[$primary]['phone'])) {
            throw ValidationException::withMessages(["guardians.{$primary}.phone" => __('The guardian who receives SMS needs a phone number.')]);
        }

        $studentFields = collect($data)->only(['sdms_id', 'reg_number', 'first_name', 'last_name', 'gender', 'date_of_birth', 'national_id', 'district', 'sector', 'special_needs', 'admission_date'])
            ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $studentFields['sdms_id'] = $studentFields['sdms_id'] ? strtoupper($studentFields['sdms_id']) : null;

        $enrollment = ! empty($data['class_id']) ? [
            'class_id' => $data['class_id'], 'enrollment_type' => $data['enrollment_type'] ?? 'new',
            'date_joined' => $data['admission_date'] ?? null, 'previous_school' => $data['previous_school'] ?? null,
            'previous_school_code' => $data['previous_school_code'] ?? null,
        ] : null;

        return [$studentFields, $guardians, $enrollment];
    }

    private function savePhoto(Request $request, Student $student): void
    {
        if ($request->boolean('remove_photo') && $student->photo_path) {
            Storage::disk('local')->delete($student->photo_path);
            $student->update(['photo_path' => null]);
        }
        if ($request->hasFile('photo')) {
            $old = $student->photo_path;
            $student->update(['photo_path' => Photo::store($request->file('photo'), 'students', $student->id)]);
            if ($old) {
                Storage::disk('local')->delete($old);
            }
        }
    }

    private function filtered(array $filters, ?AcademicYear $year)
    {
        return Student::query()
            ->search($filters['search'])
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['gender'], fn ($q, $g) => $q->where('gender', $g))
            ->when($filters['class_id'] === -1 && $year, fn ($q) => $q->whereDoesntHave('enrollments', fn ($e) => $e->where('academic_year_id', $year->id)))
            ->when($filters['class_id'] > 0, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('class_id', $filters['class_id'])));
    }

    public function row(Student $s): array
    {
        $guardian = $s->relationLoaded('primaryGuardian') ? $s->primaryGuardian->first() : null;
        $enrollment = $s->relationLoaded('currentEnrollment') ? $s->currentEnrollment : $s->currentEnrollment()->with('schoolClass:id,name')->first();

        return [
            'id' => $s->id, 'full_name' => $s->full_name, 'first_name' => $s->first_name, 'last_name' => $s->last_name,
            'sdms_id' => $s->sdms_id, 'reg_number' => $s->reg_number, 'gender' => $s->gender,
            'age' => $s->date_of_birth?->age, 'status' => $s->status, 'status_label' => __(Student::STATUSES[$s->status] ?? $s->status),
            'class' => $enrollment?->schoolClass?->name, 'class_id' => $enrollment?->class_id,
            'enrollment_status' => $enrollment?->status,
            'guardian' => $guardian ? ['name' => $guardian->full_name, 'phone' => Phone::display($guardian->phone)] : null,
            'photo_url' => $s->photo_path ? route('students.photo', [$s, 'v' => substr(md5($s->photo_path), 0, 6)]) : null,
        ];
    }

    private function classOptions(?AcademicYear $year)
    {
        if (! $year) {
            return [];
        }

        return SchoolClass::query()->where('academic_year_id', $year->id)->with('level:id,order_no')
            ->withCount('activeEnrollments')->get()
            ->sortBy([fn ($a, $b) => $a->level->order_no <=> $b->level->order_no, fn ($a, $b) => strcmp($a->name, $b->name)])
            ->map(fn (SchoolClass $c) => ['value' => $c->id, 'label' => $c->name, 'students' => $c->active_enrollments_count, 'capacity' => $c->capacity])
            ->values();
    }

    private function statusOptions()
    {
        return collect(Student::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => __($l)])->values();
    }
}
