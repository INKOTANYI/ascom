<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Academic years with exactly three terms each (BR-19); one current year and term (BR-02). */
class CalendarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setup/Calendar', [
            'years' => AcademicYear::query()->with('terms')->withCount('classes')->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateYear($request);
        DB::transaction(function () use ($data) {
            $year = AcademicYear::query()->create(['name' => $data['name'], 'start_date' => $data['terms'][0]['start_date'], 'end_date' => $data['terms'][2]['end_date']]);
            foreach ($data['terms'] as $i => $term) {
                $year->terms()->create(['term_number' => $i + 1, ...$term]);
            }
        });

        return back()->with('success', __('Academic year added with its three terms.'));
    }

    public function update(Request $request, AcademicYear $year): RedirectResponse
    {
        $data = $this->validateYear($request, $year);
        DB::transaction(function () use ($year, $data) {
            $year->update(['name' => $data['name'], 'start_date' => $data['terms'][0]['start_date'], 'end_date' => $data['terms'][2]['end_date']]);
            foreach ($data['terms'] as $i => $term) {
                $year->terms()->updateOrCreate(['term_number' => $i + 1], $term);
            }
        });
        Cache::forget('current_term_label');

        return back()->with('success', __('Saved.'));
    }

    public function makeCurrent(Term $term): RedirectResponse
    {
        $term->makeCurrent();
        Cache::forget('current_term_label');

        return back()->with('success', __('Term :n of :year is now the current term.', ['n' => $term->term_number, 'year' => $term->academicYear->name]));
    }

    public function destroy(AcademicYear $year): RedirectResponse
    {
        if ($year->is_current || $year->classes()->exists()) {
            return back()->with('error', __('This year is current or already has classes, so it cannot be deleted.'));
        }
        DB::transaction(function () use ($year) {
            $year->terms()->delete();
            $year->delete();
        });

        return back()->with('success', __('Deleted.'));
    }

    private function validateYear(Request $request, ?AcademicYear $year = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'regex:/^\d{4}(-\d{4})?$/', \Illuminate\Validation\Rule::unique('academic_years', 'name')->ignore($year?->id)],
            'terms' => ['required', 'array', 'size:3'],
            'terms.*.start_date' => ['required', 'date'],
            'terms.*.end_date' => ['required', 'date'],
        ], ['name.regex' => __('Use the form 2026-2027 or 2027.')]);

        // Each term ends after it starts, and the terms follow each other without overlapping.
        $previousEnd = null;
        foreach ($data['terms'] as $i => $term) {
            if ($term['end_date'] < $term['start_date']) {
                throw ValidationException::withMessages(["terms.$i.end_date" => __('A term must end after it starts.')]);
            }
            if ($previousEnd && $term['start_date'] <= $previousEnd) {
                throw ValidationException::withMessages(["terms.$i.start_date" => __('Terms must follow each other without overlapping.')]);
            }
            $previousEnd = $term['end_date'];
        }

        return $data;
    }
}
