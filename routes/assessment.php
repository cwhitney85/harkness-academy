<?php

use App\Http\Controllers\Assessment\HomeworkAnswerController;
use App\Http\Controllers\Assessment\HomeworkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assessment Routes (spec 6.2, 6.3, 6.4, 6.5)
|--------------------------------------------------------------------------
|
| Everything a student answers that is not a checkpoint. Checkpoints stay in
| routes/lessons.php because they are part of a lesson turn (5.2.4); these are
| what happens around a lesson rather than inside one.
|
| Every route is session-authenticated and authorizes through a policy. Package
| 7 owns the screens these feed; what package 6 owes is the wire.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('lessons/{lesson}/homework', [HomeworkController::class, 'show'])
        ->name('lessons.homework.show');

    Route::post('lessons/{lesson}/homework/answers', [HomeworkAnswerController::class, 'store'])
        ->name('lessons.homework.answers.store');
});
