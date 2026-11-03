<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Combination;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Classes of one academic year, their class teacher, and the subjects (with teachers) each class takes. */
class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $request->integer('year') ? AcademicYear::query()->findOrFail($request->integer('year')) : (AcademicYear::current() ?? AcademicYear::query()->latest('start_date')->first());

        $classes = $year ? SchoolClass::query()->where('academic_year_id', $year->id)
            ->with(['level:id,code,name,cycle,order_no', 'combination:id,code,name', 'classTeacher:id,first_name,last_name'])
            ->withCount(['classSubjects', 'activeEnrollments'])
            ->get()
            ->sortBy(fn ($c) => [$c->level->order_no, $c->name])->values() : collect();

        return Inertia::render('Setup/Classes', [
            'year' => $year?->only(['id', 'name']),
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => $classes,
            'levels' => Level::query()->where('is_offered', true)->orderBy('order_no')->get(['id', 'code', 'name', 'cycle']),
            'combinations' => Combination::query()->where('is_offered', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'teachers' => $this->teachers(),
            'canManage' => $request->user()->can('structure.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        SchoolClass::query()->create($data);

        return back()->with('success', __('Class :name added.', ['name' => $data['name']]));
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $this->validated($request, $class);
        $class->update($data);

        return back()->with('success', __('Saved.'));
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        try {
            $class->delete();
        } catch (QueryException) {
            return back()->with('error', __('This class already has students or marks and cannot be deleted.'));
        }

        return back()->with('success', __('Deleted.'));
    }

    /** The subjects one class takes, with teachers (CAMIS course assignments). */
    public function subjects(Request $request, SchoolClass $class): Response
    {
        $class->load(['level:id,code,name', 'combination:id,code,name', 'academicYear:id,name', 'classTeacher:id,first_name,last_name']);

        return Inertia::render('Setup/ClassSubjects', [
            'schoolClass' => $class,
            'items' => $class->classSubjects()->with(['subject:id,code,name,course_type,camis_course_code', 'teacher:id,first_name,last_name'])->get()
                ->sortBy('subject.name')->values(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'teachers' => $this->teachers(),
            // Classes of the same year that already have subjects, to copy from (e.g. S1 A to S1 B).
            'copyFrom' => SchoolClass::query()->where('academic_year_id', $class->academic_year_id)->where('id', '!=', $class->id)
                ->has('classSubjects')->withCount('classSubjects')->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('structure.manage'),
        ]);
    }

    public function storeSubject(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $this->validatedSubject($request, $class);
        $class->classSubjects()->create($data);

        return back()->with('success', __('Saved.'));
    }

    public function updateSubject(Request $request, SchoolClass $class, ClassSubject $item): RedirectResponse
    {
        abort_unless($item->class_id === $class->id, 404);
        $item->update($this->validatedSubject($request, $class, $item));

        return back()->with('success', __('Saved.'));
    }

    public function destroySubject(SchoolClass $class, ClassSubject $item): RedirectResponse
    {
        abort_unless($item->class_id === $class->id, 404);
        try {
            $item->delete();
        } catch (QueryException) {
            return back()->with('error', __('This subject already has assessments for this class and cannot be removed.'));
        }

        return back()->with('success', __('Deleted.'));
    }

    /** Copy every subject of a class to another class of the same level, keeping existing ones. */
    public function copySubjects(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $request->validate(['from_class_id' => ['required', 'exists:classes,id']]);
        $from = SchoolClass::query()->findOrFail($data['from_class_id']);
        $added = 0;
        foreach ($from->classSubjects as $item) {
            $created = $class->classSubjects()->firstOrCreate(['subject_id' => $item->subject_id], [
                'weekly_periods' => $item->weekly_periods, 'max_marks' => $item->max_marks,
            ]);
            $added += $created->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', __('Subjects copied: :n. Assign their teachers.', ['n' => $added]));
    }

    private function validated(Request $request, ?SchoolClass $class = null): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'level_id' => ['required', 'exists:levels,id'],
            'combination_id' => ['nullable', 'exists:combinations,id'],
            'stream' => ['nullable', 'string', 'max:5', 'regex:/^[A-Za-z0-9]+$/'],
            'class_teacher_id' => ['nullable', 'exists:staff,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $data += ['combination_id' => null, 'stream' => null, 'class_teacher_id' => null, 'capacity' => null];
        $level = Level::query()->findOrFail($data['level_id']);
        $combination = $data['combination_id'] ? Combination::query()->find($data['combination_id']) : null;

        // A-level classes need a combination and TVET classes a trade; other levels have neither.
        $needs = ['a_level' => 'a_level', 'tvet' => 'tvet_trade'][$level->cycle] ?? null;
        if ($needs && (! $combination || $combination->type !== $needs)) {
            throw ValidationException::withMessages(['combination_id' => $needs === 'a_level' ? __('Choose the A-level combination.') : __('Choose the TVET trade.')]);
        }
        if (! $needs) {
            $combination = null;
            $data['combination_id'] = null;
        }

        $data['stream'] = $data['stream'] ? strtoupper($data['stream']) : null;
        $data['name'] = SchoolClass::makeName($level, $combination, $data['stream']);
        $exists = SchoolClass::query()->where('academic_year_id', $data['academic_year_id'])->where('name', $data['name'])
            ->when($class, fn ($q) => $q->where('id', '!=', $class->id))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['stream' => __('Class :name already exists this year. Use another stream letter.', ['name' => $data['name']])]);
        }

        return $data;
    }

    private function validatedSubject(Request $request, SchoolClass $class, ?ClassSubject $item = null): array
    {
        return $request->validate([
            'subject_id' => ['required', 'exists:subjects,id', Rule::unique('class_subjects', 'subject_id')->where('class_id', $class->id)->ignore($item?->id)],
            'teacher_id' => ['nullable', 'exists:staff,id'],
            'weekly_periods' => ['nullable', 'integer', 'min:0', 'max:40'],
            'max_marks' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'sdms_synced' => ['boolean'],
        ], ['subject_id.unique' => __('This class already takes that subject.')]);
    }

    private function teachers()
    {
        return Staff::query()->where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'position']);
    }
}
