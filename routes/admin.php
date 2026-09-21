<?php

use App\Http\Controllers\Admin\ActionLogController;
use App\Http\Controllers\Admin\AiModelController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\ViewAsController;
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

        // The model catalog, its tiers, and what each model has cost (14.4).
        Route::get('models', [AiModelController::class, 'index'])->name('admin.models');
        Route::put('models/{model}', [AiModelController::class, 'update'])->name('admin.models.update');

        // The 2.3.2 inventory, and the one place a prompt's text is written
        // (15.2, ledger D65).
        Route::get('prompts', [PromptController::class, 'index'])->name('admin.prompts');
        Route::get('prompts/{prompt}', [PromptController::class, 'show'])->name('admin.prompts.show');
        Route::put('prompts/{prompt}', [PromptController::class, 'update'])->name('admin.prompts.update');

        // Viewing as a student (13.4). Step-up and Exit are the only two
        // `/admin*` routes reachable while viewing (13.4.6); `ViewAsStudent`
        // names them.
        Route::post('users/{user}/view-as', [ViewAsController::class, 'store'])->name('admin.users.view-as');
        Route::post('view-as/step-up', [ViewAsController::class, 'update'])->name('admin.view-as.step-up');
        Route::delete('view-as', [ViewAsController::class, 'destroy'])->name('admin.view-as.exit');
    });
