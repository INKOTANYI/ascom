<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Step 1 dashboard: the school's setup progress and the current term. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $year = AcademicYear::current();
        $term = Term::current();
        $classIds = $year ? SchoolClass::query()->where('academic_year_id', $year->id)->pluck('id') : collect();
        $classSubjects = ClassSubject::query()->whereIn('class_id', $classIds);
        $school = SchoolProfile::current();

        $steps = [
            ['key' => 'profile', 'label' => __('School profile and logo'), 'done' => ! Setting::get('example_data') && (bool) $school?->logo_path, 'route' => 'setup.settings', 'permission' => 'settings.manage'],
            ['key' => 'calendar', 'label' => __('Academic year and three terms'), 'done' => (bool) $term, 'route' => 'setup.calendar', 'permission' => 'calendar.manage'],
            ['key' => 'levels', 'label' => __('Levels this school offers'), 'done' => Level::query()->where('is_offered', true)->exists(), 'route' => 'setup.levels', 'permission' => 'structure.manage'],
            ['key' => 'staff', 'label' => __('Staff and teachers'), 'done' => Staff::query()->exists(), 'route' => 'setup.staff', 'permission' => 'staff.manage'],
            ['key' => 'classes', 'label' => __('Classes for this year'), 'done' => $classIds->isNotEmpty(), 'route' => 'setup.classes', 'permission' => 'structure.manage'],
            ['key' => 'subjects', 'label' => __('Subjects and teachers for each class'), 'done' => (clone $classSubjects)->whereNotNull('teacher_id')->exists(), 'route' => 'setup.classes', 'permission' => 'structure.manage'],
            ['key' => 'students', 'label' => __('Students in their classes'), 'done' => $year && Student::query()->whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $year->id))->exists(), 'route' => 'students.index', 'permission' => 'students.manage'],
            ['key' => 'users', 'label' => __('Accounts for the staff'), 'done' => User::query()->count() > 1, 'route' => 'setup.users', 'permission' => 'users.manage'],
        ];

        return Inertia::render('Dashboard', [
            'term' => $term ? ['number' => $term->term_number, 'year' => $year?->name, 'start' => $term->start_date->toDateString(), 'end' => $term->end_date->toDateString()] : null,
            'stats' => [
                'students' => Student::query()->where('status', 'active')->count(),
                'classes' => $classIds->count(),
                'staff' => Staff::query()->where('is_active', true)->count(),
                'subjects' => Subject::query()->where('is_active', true)->count(),
                'unassigned' => (clone $classSubjects)->whereNull('teacher_id')->count(),
            ],
            'steps' => collect($steps)->filter(fn ($s) => $request->user()->can($s['permission']))->values(),
        ]);
    }
}
