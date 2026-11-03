<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** English, French or Kinyarwanda (NFR-08): the visitor's choice, else the user's, else the school default. */
class SetLocale
{
    public const LOCALES = ['en' => 'English', 'fr' => 'Français', 'rw' => 'Kinyarwanda'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->user()?->language
            ?? Setting::get('default_language', 'en');

        app()->setLocale(array_key_exists($locale, self::LOCALES) ? $locale : 'en');

        return $next($request);
    }
}
