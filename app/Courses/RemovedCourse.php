<?php

namespace App\Courses;

use App\Courses\Exceptions\CourseRemoved;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What a student meets on a screen of a course an admin removed (spec
 * 16.2.4, ledger D70): the dashboard, told why. The same whether they
 * followed a link to it or were waiting on it when it went.
 *
 * Only their own course: to anyone else a removed course is a 404 like any
 * other, so the message says nothing about whether it existed.
 */
final class RemovedCourse
{
    public static function response(NotFoundHttpException|CourseRemoved $e, Request $request): RedirectResponse|JsonResponse|null
    {
        $course = $e instanceof CourseRemoved
            ? Course::withTrashed()->find($e->courseId)
            : self::behind($e->getPrevious());

        if ($course === null || ! $course->trashed() || $course->user_id !== $request->user()?->id) {
            return null;
        }

        $message = "{$course->subtopic_title} ({$course->skill_level->title()}) has been removed from your courses.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 404);
        }

        Inertia::flash('toast', ['type' => 'info', 'message' => $message]);

        return redirect()->route('dashboard');
    }

    /**
     * The course behind a lesson, refresher or course URL that bound nothing.
     */
    private static function behind(?\Throwable $missing): ?Course
    {
        if (! $missing instanceof ModelNotFoundException) {
            return null;
        }

        $id = $missing->getIds()[0] ?? null;

        return match ($missing->getModel()) {
            Course::class => Course::withTrashed()->find($id),
            Lesson::class => Lesson::query()->find($id)?->course()->withTrashed()->first(),
            Quiz::class => Quiz::query()->find($id)?->course()->withTrashed()->first(),
            default => null,
        };
    }
}
