<?php

namespace App\Http\Middleware;

use App\Models\SchoolProfile;
use App\Models\Setting;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * The school's identity comes from Settings, so the same code serves any school.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $school = SchoolProfile::current();
        $settings = Setting::values();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role?->name,
                    'role_label' => $user->role?->label ? __($user->role->label) : null,
                ] : null,
                'can' => $user ? array_values(array_unique($user->isAdmin() ? ['*'] : $user->permissionCodes())) : [],
            ],
            'school' => [
                'name' => $school?->name ?? config('app.name'),
                'short_name' => $school?->short_name ?: $school?->name,
                'motto' => $school?->motto,
                'logo_url' => $school?->logo_path ? route('school.logo', ['v' => $school->updated_at?->timestamp]) : null,
                'brand_color' => $settings['brand_color'],
                'accent_color' => $settings['accent_color'],
                'example_data' => (bool) $settings['example_data'],
            ],
            'calendar' => fn () => $user ? Cache::remember('current_term_label', 300, function () {
                $term = Term::current()?->load('academicYear');

                return $term ? ['year' => $term->academicYear->name, 'term' => $term->term_number] : null;
            }) : null,
            'locale' => $locale,
            'locales' => SetLocale::LOCALES,
            'translations' => fn () => $this->translations($locale),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** UI strings for French and Kinyarwanda; English text is its own key. */
    private function translations(string $locale): array
    {
        if ($locale === 'en') {
            return [];
        }
        $file = lang_path("{$locale}.json");

        return is_file($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    }
}
