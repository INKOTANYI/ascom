<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\SchoolProfile;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The school's identity and preferences. Changing them here changes every screen,
 * and later the report cards, receipts and SMS.
 */
class SettingsController extends Controller
{
    public function edit(): Response
    {
        $school = SchoolProfile::current();

        return Inertia::render('Setup/Settings', [
            'profile' => $school?->only([
                'name', 'short_name', 'mineduc_code', 'school_type', 'ownership', 'province', 'district', 'sector',
                'phone', 'email', 'motto', 'domain', 'is_boarding',
            ]),
            'settings' => collect(Setting::values())->only(['brand_color', 'accent_color', 'default_language', 'sms_sender', 'example_data']),
            'options' => [
                'types' => collect(SchoolProfile::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => __($l)])->values(),
                'ownership' => collect(SchoolProfile::OWNERSHIP)->map(fn ($l, $v) => ['value' => $v, 'label' => __($l)])->values(),
                'provinces' => ['Kigali City', 'Eastern Province', 'Northern Province', 'Southern Province', 'Western Province'],
                'languages' => collect(SetLocale::LOCALES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = SchoolProfile::current() ?? new SchoolProfile();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:40'],
            'mineduc_code' => ['nullable', 'string', 'max:20', Rule::unique('school_profile', 'mineduc_code')->ignore($school->id)],
            'school_type' => ['required', Rule::in(array_keys(SchoolProfile::TYPES))],
            'ownership' => ['nullable', Rule::in(array_keys(SchoolProfile::OWNERSHIP))],
            'province' => ['nullable', 'string', 'max:50'],
            'district' => ['required', 'string', 'max:50'],
            'sector' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'motto' => ['nullable', 'string', 'max:200'],
            'domain' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'is_boarding' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_language' => ['required', Rule::in(array_keys(SetLocale::LOCALES))],
            'sms_sender' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]*$/'],
        ]);

        $profile = collect($data)->except(['logo', 'remove_logo', 'brand_color', 'accent_color', 'default_language', 'sms_sender'])->all();
        if ($request->hasFile('logo')) {
            $old = $school->logo_path;
            $profile['logo_path'] = $request->file('logo')->store('school', 'local');
            if ($old) {
                Storage::disk('local')->delete($old);
            }
        } elseif ($request->boolean('remove_logo') && $school->logo_path) {
            Storage::disk('local')->delete($school->logo_path);
            $profile['logo_path'] = null;
        }
        $school->fill($profile)->save();

        foreach (['brand_color', 'accent_color', 'default_language', 'sms_sender'] as $key) {
            Setting::put($key, $data[$key] ?? '');
        }
        // The real school details are now entered: stop showing the "example data" notice.
        if (! str_contains(strtolower($data['district']), 'to be completed')) {
            Setting::put('example_data', false);
        }

        return back()->with('success', __('Settings saved. Every screen now uses them.'));
    }

    /** The logo is kept outside the public folder and served here (works without storage links). */
    public function logo(): StreamedResponse
    {
        $path = SchoolProfile::current()?->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
