<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Accounts and roles (FR-03). The school creates every account; there is no public sign-up. */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $users = User::query()->with(['role:id,name,label', 'staff:id,user_id,first_name,last_name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$search}%")
                ->orWhere('username', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('role', fn ($r) => $r->where('name', $role)))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(50)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'email' => $u->email,
                'phone' => $u->phone ? substr($u->phone, 4) : null, 'phone_display' => $u->phone,
                'role_id' => $u->role_id, 'role' => $u->role?->name, 'role_label' => $u->role?->label ? __($u->role->label) : null,
                'language' => $u->language, 'is_active' => $u->is_active,
                'staff_id' => $u->staff?->id, 'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('Setup/Users', [
            'users' => $users,
            'search' => $search,
            'roleFilter' => $request->query('role'),
            // Only the system administrator can create another administrator.
            'roles' => Role::query()->when(! $request->user()->isAdmin(), fn ($q) => $q->where('name', '!=', 'admin'))->orderBy('id')
                ->get(['id', 'name', 'label'])->map(fn ($r) => ['value' => $r->id, 'name' => $r->name, 'label' => __($r->label)]),
            'staff' => Staff::query()->whereNull('user_id')->where('is_active', true)->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'phone', 'position']),
            'languages' => collect(SetLocale::LOCALES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $user = User::query()->create($data['user']);
            if ($data['staff_id']) {
                Staff::query()->whereKey($data['staff_id'])->whereNull('user_id')->update(['user_id' => $user->id]);
            }
        });

        return back()->with('success', __('Account created. Give the person their username or phone and password.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardAdmin($request, $user);
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id && (! $data['user']['is_active'] || (int) $data['user']['role_id'] !== $user->role_id)) {
            return back()->with('error', __('You cannot disable your own account or change your own role.'));
        }
        DB::transaction(function () use ($user, $data) {
            $user->update($data['user']);
            if ($data['staff_id']) {
                Staff::query()->whereKey($data['staff_id'])->whereNull('user_id')->update(['user_id' => $user->id]);
            }
        });

        return back()->with('success', __('Saved.'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['phone' => $request->filled('phone') ? preg_replace('/\D/', '', (string) $request->input('phone')) : null]);
        $allowedRoles = Role::query()->when(! $request->user()->isAdmin(), fn ($q) => $q->where('name', '!=', 'admin'))->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^7[2389]\d{7}$/'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['required', Rule::in($allowedRoles)],
            'language' => ['required', Rule::in(array_keys(SetLocale::LOCALES))],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'staff_id' => ['nullable', 'exists:staff,id'],
        ], [
            'username.regex' => __('Use lowercase letters, numbers, dots, dashes or underscores.'),
            'phone.regex' => __('Enter the 9 digits after +250, starting with 72, 73, 78 or 79.'),
        ]);

        $phone = Phone::normalize($data['phone'] ?? null);
        if ($phone && User::query()->where('phone', $phone)->when($user, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone' => __('This phone number already has an account.')]);
        }
        if (empty($data['username']) && ! $phone && empty($data['email'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['username' => __('Give at least a username, a phone number or an email to sign in with.')]);
        }

        $fields = collect($data)->only(['name', 'username', 'email', 'role_id', 'language'])->all();
        $fields['phone'] = $phone;
        $fields['username'] = $fields['username'] ?? null;
        $fields['email'] = ! empty($fields['email']) ? strtolower($fields['email']) : null;
        $fields['is_active'] = $request->boolean('is_active', true);
        if (! empty($data['password'])) {
            $fields['password'] = $data['password'];
        }

        return ['user' => $fields, 'staff_id' => $data['staff_id'] ?? null];
    }

    /** Only an administrator can change an administrator's account. */
    private function guardAdmin(Request $request, User $user): void
    {
        abort_if($user->isAdmin() && ! $request->user()->isAdmin(), 403);
    }
}
