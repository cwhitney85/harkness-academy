<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Demo\DemoConversionController;
use App\Http\Controllers\Demo\DemoEntryController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('terms', 'terms')->name('terms');

// Screen S0 (spec 8.13), and the edge 8.6's map draws for the entry URL:
// signed in --> S1, otherwise the landing page. It sits outside the auth group
// because the middleware would send every visitor to sign in and there would
// be no front door left to reach (8.13.2); the decision it used to make is now
// the one condition in the route.
Route::get('/', fn () => Auth::check()
    ? redirect()->route('dashboard')
    : Inertia::render('landing'))->name('home');

// Screen S13 (spec 12.1): mints a `demo`-plan account and lands on S2. Outside
// the auth group for the reason `home` is -- the visitor has no account yet.
Route::get('demo', DemoEntryController::class)->name('demo');

Route::middleware(['auth', 'verified'])->group(function () {
    // Screen S1 (spec 8.10.1). No longer `Route::inertia`: the dashboard is
    // the student's courses and the next move in each, which needs a query.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Screen S12 (spec 8.10.2). A module is catalog rather than a course, so
    // it sits here beside the dashboard rather than in routes/courses.php.
    Route::get('modules/{module}', ModuleController::class)->name('modules.show');

    // Spec 7.1's install-instructions page. Static, so `Route::inertia` as
    // `terms` is; signed in, because it is reached from the user menu and set
    // in the same `AppLayout` the dashboard is.
    Route::inertia('install', 'install')->name('install');

    // Screen S14 (spec 12.4): a demo visitor keeps what they built.
    Route::get('demo/account', [DemoConversionController::class, 'create'])->name('demo.account.create');
    Route::post('demo/account', [DemoConversionController::class, 'store'])->name('demo.account.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/courses.php';
require __DIR__.'/lessons.php';
require __DIR__.'/assessment.php';
