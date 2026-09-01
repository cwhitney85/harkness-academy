<?php

use App\Courses\Interviews\InterviewKind;
use App\Http\Controllers\Courses\CourseController;
use App\Http\Controllers\Courses\CourseResumptionController;
use App\Http\Controllers\Courses\InterviewAnswerController;
use App\Http\Controllers\Courses\InterviewCompletionController;
use App\Http\Controllers\Courses\InterviewController;
use App\Http\Controllers\Courses\InterviewStreamController;
use App\Http\Controllers\Courses\SyllabusApprovalController;
use App\Http\Controllers\Courses\SyllabusController;
use App\Http\Controllers\Courses\SyllabusRevisionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Course Pipeline Routes (spec 8.7, decided in 8.6)
|--------------------------------------------------------------------------
|
| Package 4's missing HTTP layer. The engine -- CreateCourse, Interviews,
| SyllabusGenerator, ApproveSyllabus, CourseTransitions -- shipped with no way
| for a student to reach any of it. These are the doors, and every URL below
| was named in the 8.6 flow map before its controller was written.
|
| Package 4 owns them by subject and package 8 by schedule.
|
| `courses.withdraw` is deliberately absent: setting a course aside is 8.10.3's
| half of the pair, and the route lands with the card that fires it. `resume`
| is here because S2's "carry on, or start over?" is one of its two doors
| (8.7.6).
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('courses/create', [CourseController::class, 'create'])
        ->name('courses.create');

    Route::post('courses', [CourseController::class, 'store'])
        ->name('courses.store');

    Route::post('courses/{course}/resume', [CourseResumptionController::class, 'store'])
        ->name('courses.resume');

    // The two interviews are the same four actions with a different kind
    // (ledger D9: one machine, two ends). The kind travels as a route default
    // rather than a URL segment, so the two literal URLs the flow map names
    // are the only two that exist.
    foreach (['knowledge' => InterviewKind::Knowledge, 'goals' => InterviewKind::Goals] as $segment => $kind) {
        $url = "courses/{course}/{$segment}-interview";
        $name = "courses.{$segment}-interview";

        Route::get($url, [InterviewController::class, 'show'])
            ->defaults('kind', $kind->value)
            ->name("{$name}.show");

        Route::get("{$url}/stream", InterviewStreamController::class)
            ->defaults('kind', $kind->value)
            ->name("{$name}.stream");

        Route::post("{$url}/answers", [InterviewAnswerController::class, 'store'])
            ->defaults('kind', $kind->value)
            ->name("{$name}.answers.store");

        Route::post("{$url}/complete", [InterviewCompletionController::class, 'store'])
            ->defaults('kind', $kind->value)
            ->name("{$name}.complete");
    }

    Route::get('courses/{course}/syllabus', [SyllabusController::class, 'show'])
        ->name('courses.syllabus.show');

    // Both generators spend a `top`-tier call, the same class of spend as
    // opening an exam, and carry the same allowance (8.6).
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('courses/{course}/syllabus', [SyllabusController::class, 'store'])
            ->name('courses.syllabus.store');

        Route::post('courses/{course}/syllabus/revisions', [SyllabusRevisionController::class, 'store'])
            ->name('courses.syllabus.revisions.store');
    });

    Route::post('courses/{course}/syllabus/approval', [SyllabusApprovalController::class, 'store'])
        ->name('courses.syllabus.approval.store');
});
