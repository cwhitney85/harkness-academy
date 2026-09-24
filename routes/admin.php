<?php

use App\Http\Controllers\Admin\ActionLogController;
use App\Http\Controllers\Admin\AiModelController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\UserController;
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
        // The admin's home is Users, where viewing-as starts (17.1). A
        // deleted account keeps its page.
        Route::get('/', [UserController::class, 'index'])->name('admin.home');
        Route::get('users/{user}', [UserController::class, 'show'])->withTrashed()->name('admin.users.show');
        Route::put('users/{user}/plan', [UserController::class, 'plan'])->name('admin.users.plan');

        Route::get('log', ActionLogController::class)->name('admin.log');

        // The catalog's modules and subtopics, what removing them hid, and
        // restoring it (16.5, ledger D57).
        Route::get('catalog', [CatalogController::class, 'index'])->name('admin.catalog.index');
        Route::put('catalog/subtopics/{subtopic}', [CatalogController::class, 'retitle'])->name('admin.catalog.retitle');
        Route::post('catalog/modules/{module}/remove', [CatalogController::class, 'removeModule'])->name('admin.catalog.modules.remove');
        Route::post('catalog/subtopics/{subtopic}/remove', [CatalogController::class, 'removeSubtopic'])->name('admin.catalog.subtopics.remove');
        Route::get('catalog/removed', [CatalogController::class, 'removed'])->name('admin.catalog.removed');
        Route::post('catalog/removals/{removal}/restore', [CatalogController::class, 'restore'])->name('admin.catalog.restore');

        // What students type that the catalog does not offer, and what the
        // scope check turned away (16.1.5, 16.1.6).
        Route::get('catalog/demand', [CatalogController::class, 'demand'])->name('admin.catalog.demand');
        Route::post('catalog/subtopics/{subtopic}/promote', [CatalogController::class, 'promote'])->name('admin.catalog.promote');
        Route::get('catalog/refusals', [CatalogController::class, 'refusals'])->name('admin.catalog.refusals');

        // The model catalog, its tiers, and what each model has cost (14.4).
        Route::get('models', [AiModelController::class, 'index'])->name('admin.models');
        Route::put('models/{model}', [AiModelController::class, 'update'])->name('admin.models.update');

        // The 2.3.2 inventory, and the one place a prompt's text is written
        // (15.2, ledger D65).
        Route::get('prompts', [PromptController::class, 'index'])->name('admin.prompts');
        Route::get('prompts/{prompt}', [PromptController::class, 'show'])->name('admin.prompts.show');
        Route::put('prompts/{prompt}', [PromptController::class, 'update'])->name('admin.prompts.update');
        Route::post('prompts/{prompt}/versions/{number}', [PromptController::class, 'activate'])
            ->whereNumber('number')
            ->name('admin.prompts.activate');

        // Viewing as a student (13.4). Step-up and Exit are the only two
        // `/admin*` routes reachable while viewing (13.4.6); `ViewAsStudent`
        // names them.
        Route::post('users/{user}/view-as', [ViewAsController::class, 'store'])->name('admin.users.view-as');
        Route::post('view-as/step-up', [ViewAsController::class, 'update'])->name('admin.view-as.step-up');
        Route::delete('view-as', [ViewAsController::class, 'destroy'])->name('admin.view-as.exit');
    });
