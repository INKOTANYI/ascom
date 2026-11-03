<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** A signed-in person's own account. Accounts are created and removed by the school, not by the user. */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role');

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone ? substr($user->phone, 4) : '',
                'language' => $user->language,
                'role_label' => $user->role?->label ? __($user->role->label) : null,
            ],
            'languages' => collect(SetLocale::LOCALES)->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'regex:/^7[2389]\d{7}$/'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'language' => ['required', Rule::in(array_keys(SetLocale::LOCALES))],
        ], [
            'phone.regex' => __('Enter the 9 digits after +250, starting with 72, 73, 78 or 79.'),
        ]);

        $phone = Phone::normalize($data['phone'] ?? null);
        if ($phone && User::query()->where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('This phone number already has an account.')]);
        }
        if (! $user->username && ! $phone && empty($data['email'])) {
            throw ValidationException::withMessages(['phone' => __('Give at least a username, a phone number or an email to sign in with.')]);
        }

        $user->fill([
            'name' => $data['name'],
            'phone' => $phone,
            'email' => ! empty($data['email']) ? strtolower($data['email']) : null,
            'language' => $data['language'],
        ]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        $request->session()->put('locale', $user->language);

        return back()->with('success', __('Your profile is saved.'));
    }
}
