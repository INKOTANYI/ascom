<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Every permission code (settings.manage, structure.view, ...) is a gate:
        // `can:structure.manage` on a route, `$user->can('structure.manage')` in code.
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);
    }
}
