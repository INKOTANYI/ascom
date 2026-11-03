<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Parents and guardians, with their children at the school. */
class GuardianController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $digits = preg_replace('/\D/', '', $search);

        $guardians = Guardian::query()
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search, $digits) {
                $like = '%'.mb_strtolower($search).'%';
                $q->whereRaw("lower(first_name || ' ' || coalesce(last_name, '')) like ?", [$like])
                    ->orWhereRaw("lower(coalesce(last_name, '') || ' ' || first_name) like ?", [$like])
                    ->orWhereHas('students', fn ($s) => $s->search($search));
                if (strlen($digits) >= 6) {
                    $q->orWhere('phone', 'like', '%'.substr($digits, -9).'%');
                }
            }))
            ->when($request->boolean('no_phone'), fn ($q) => $q->whereNull('phone'))
            ->with(['students' => fn ($q) => $q->select('students.id', 'first_name', 'last_name', 'status')->with('currentEnrollment.schoolClass:id,name')])
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(25)->withQueryString()
            ->through(fn (Guardian $g) => [
                'id' => $g->id, 'full_name' => $g->full_name, 'first_name' => $g->first_name, 'last_name' => $g->last_name ?? '',
                'phone' => Phone::local($g->phone), 'phone_display' => Phone::display($g->phone), 'email' => $g->email ?? '',
                'national_id' => $g->national_id ?? '', 'occupation' => $g->occupation ?? '', 'has_login' => (bool) $g->user_id,
                'children' => $g->students->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->full_name, 'class' => $s->currentEnrollment?->schoolClass?->name,
                    'relationship' => $s->pivot->relationship, 'is_primary' => (bool) $s->pivot->is_primary, 'status' => $s->status,
                ]),
            ]);

        return Inertia::render('Students/Guardians', [
            'guardians' => $guardians,
            'search' => $search,
            'noPhone' => $request->boolean('no_phone'),
            'canManage' => $request->user()->can('students.manage'),
        ]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'regex:/^7[2389]\d{7}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:80'],
        ], ['phone.regex' => __('Enter the 9 digits after +250, starting with 72, 73, 78 or 79.')]);

        $isPrimary = $guardian->students()->wherePivot('is_primary', true)->exists();
        if ($isPrimary && empty($data['phone'])) {
            return back()->withErrors(['phone' => __('The guardian who receives SMS needs a phone number.')]);
        }

        $guardian->update([
            ...$data,
            'phone' => Phone::normalize($data['phone'] ?? null),
            'email' => ! empty($data['email']) ? strtolower($data['email']) : null,
        ]);

        return back()->with('success', __('Saved.'));
    }
}
