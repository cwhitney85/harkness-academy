<?php

use App\Http\Controllers\Lessons\CheckpointAnswerController;
use App\Http\Controllers\Lessons\LessonCompletionController;
use App\Http\Controllers\Lessons\LessonController;
use App\Http\Controllers\Lessons\LessonQuestionController;
use App\Http\Controllers\Lessons\SectionCheckpointController;
use App\Http\Controllers\Lessons\SectionStreamController;
use App\Http\Controllers\Lessons\SelectionExplanationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Lesson Engine Routes (spec 5.2.4, 5.2.6, 5.3, 5.4.1, 5.5)
|--------------------------------------------------------------------------
|
| The two URLs spec 5 names verbatim are here as written -- the section stream
| and the checkpoint answer -- plus the state a resume reads (5.3), the Q&A
| turn of ledger D5, completion (5.4.1) and the side-tab (5.5).
|
| Every route is session-authenticated and every controller authorizes through
| LessonPolicy. Package 7 owns the screens these feed; what package 5 owes is
| the wire.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('lessons/{lesson}', [LessonController::class, 'show'])
        ->name('lessons.show');

    Route::get('lessons/{lesson}/sections/{index}/stream', SectionStreamController::class)
        ->whereNumber('index')
        ->name('lessons.sections.stream');

    Route::post('lessons/{lesson}/questions', [LessonQuestionController::class, 'store'])
        ->name('lessons.questions.store');

    // The one route that spends tokens without advancing anything, so it is
    // the one route a bored client could hold open indefinitely. The throttle
    // is the cost boundary; `usage_events` is the receipt.
    Route::post('lessons/{lesson}/explanations', [SelectionExplanationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('lessons.explanations.store');

    Route::post('lessons/{lesson}/checkpoints/{checkpoint}/answers', [CheckpointAnswerController::class, 'store'])
        ->name('lessons.checkpoints.answers.store');

    Route::post('lessons/{lesson}/sections/{index}/checkpoint', [SectionCheckpointController::class, 'store'])
        ->whereNumber('index')
        ->name('lessons.sections.checkpoint.store');

    Route::post('lessons/{lesson}/complete', [LessonCompletionController::class, 'store'])
        ->name('lessons.complete');
});
