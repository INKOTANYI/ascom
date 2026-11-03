<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Setup\CalendarController;
use App\Http\Controllers\Setup\ClassController;
use App\Http\Controllers\Setup\CombinationController;
use App\Http\Controllers\Setup\LevelController;
use App\Http\Controllers\Setup\SettingsController;
use App\Http\Controllers\Setup\StaffController;
use App\Http\Controllers\Setup\SubjectController;
use App\Http\Controllers\Setup\TopicController;
use App\Http\Controllers\Setup\UserController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

// The school logo is public (login page, report cards later).
Route::get('/school-logo', [SettingsController::class, 'logo'])->name('school.logo');

// Language switch for everyone, signed in or not.
Route::post('/language', function (Request $request) {
    $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(SetLocale::LOCALES))]]);
    $request->session()->put('locale', $data['locale']);
    $request->user()?->update(['language' => $data['locale']]);

    return back();
})->name('language');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('setup')->name('setup.')->group(function () {
        Route::middleware('can:settings.manage')->group(function () {
            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
            Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('can:calendar.manage')->group(function () {
            Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
            Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
            Route::put('/calendar/{year}', [CalendarController::class, 'update'])->name('calendar.update');
            Route::delete('/calendar/{year}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
            Route::post('/terms/{term}/current', [CalendarController::class, 'makeCurrent'])->name('terms.current');
        });

        // Simple lists: levels, combinations, subjects, topics, staff (each checks its own permissions).
        foreach (['levels' => LevelController::class, 'combinations' => CombinationController::class, 'subjects' => SubjectController::class,
            'topics' => TopicController::class, 'staff' => StaffController::class] as $path => $controller) {
            Route::get("/{$path}", [$controller, 'index'])->name($path);
            Route::post("/{$path}", [$controller, 'store'])->name("{$path}.store");
            Route::put("/{$path}/{id}", [$controller, 'update'])->whereNumber('id')->name("{$path}.update");
            Route::delete("/{$path}/{id}", [$controller, 'destroy'])->whereNumber('id')->name("{$path}.destroy");
        }

        Route::get('/classes', [ClassController::class, 'index'])->middleware('can:structure.view')->name('classes');
        Route::get('/classes/{class}/subjects', [ClassController::class, 'subjects'])->middleware('can:structure.view')->name('classes.subjects');
        Route::middleware('can:structure.manage')->group(function () {
            Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
            Route::put('/classes/{class}', [ClassController::class, 'update'])->name('classes.update');
            Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');
            Route::post('/classes/{class}/subjects', [ClassController::class, 'storeSubject'])->name('classes.subjects.store');
            Route::post('/classes/{class}/subjects/copy', [ClassController::class, 'copySubjects'])->name('classes.subjects.copy');
            Route::put('/classes/{class}/subjects/{item}', [ClassController::class, 'updateSubject'])->name('classes.subjects.update');
            Route::delete('/classes/{class}/subjects/{item}', [ClassController::class, 'destroySubject'])->name('classes.subjects.destroy');
        });

        Route::middleware('can:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });
    });
});

require __DIR__.'/auth.php';
