<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::inertia('terms', 'terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    // The entry URL, and the edge 8.6's map has always drawn for it:
    // `home | entry --> S1`. It sits inside the auth group so the decision is
    // made once at the boundary -- a student lands on their dashboard, a guest
    // is sent to sign in by the middleware, and neither is asked to click
    // through a page in between.
    Route::redirect('/', '/dashboard')->name('home');

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
});

require __DIR__.'/settings.php';
require __DIR__.'/courses.php';
require __DIR__.'/lessons.php';
require __DIR__.'/assessment.php';
