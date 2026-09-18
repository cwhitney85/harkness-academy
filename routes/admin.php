<?php

use App\Http\Controllers\Admin\ActionLogController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Middleware\RequireSecondFactor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes (spec 13)
|--------------------------------------------------------------------------
|
| Every route here is `/admin*`, behind the one `admin` gate (13.1.2) and a
| confirmed second factor (13.1.5). Every non-GET route writes exactly one
| `admin_actions` row through `ActionLog` (13.2.2).
|
*/

Route::middleware(['auth', 'verified', 'can:admin', RequireSecondFactor::class])
    ->prefix('admin')
    ->group(function (): void {
        // Until 17.1 builds Users, the admin's home is the list of students
        // viewing-as starts from (17.1.4).
        Route::get('/', [StudentController::class, 'index'])->name('admin.home');

        Route::get('log', ActionLogController::class)->name('admin.log');
    });
