<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::inertia('terms', 'terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    // Screen S1 (spec 8.10.1). No longer `Route::inertia`: the dashboard is
    // the student's courses and the next move in each, which needs a query.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Screen S12 (spec 8.10.2). A module is catalog rather than a course, so
    // it sits here beside the dashboard rather than in routes/courses.php.
    Route::get('modules/{module}', ModuleController::class)->name('modules.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/courses.php';
require __DIR__.'/lessons.php';
require __DIR__.'/assessment.php';
