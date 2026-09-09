<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Hand every message a field failed on to the parent, not just the first.
     *
     * @var bool
     */
    protected $withAllErrors = true;

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Report every message a field failed on, as one string.
     *
     * Inertia keeps `errors.field` a string by dropping all but the first
     * message, so a password that breaks two rules names one of them and the
     * student learns the rest one submission at a time (spec 1.5). Joining
     * them here names them all without changing the shape a screen reads.
     *
     * Post: every field is a single string, as Inertia's default promises.
     */
    public function resolveValidationErrors(Request $request): object
    {
        return $this->joinMessages(parent::resolveValidationErrors($request));
    }

    /**
     * Pre:  each value is a list of messages, or -- under a named error bag --
     *       an object of fields holding those lists.
     */
    private function joinMessages(object $errors): object
    {
        return (object) array_map(
            fn (array|object $messages): string|object => is_array($messages)
                ? implode(' ', $messages)
                : $this->joinMessages($messages),
            get_object_vars($errors),
        );
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
