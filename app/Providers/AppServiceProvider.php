<?php

namespace App\Providers;

use App\Ai\Prompts\PromptFiles;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PromptFiles::class,
            fn (): PromptFiles => new PromptFiles(base_path('prompts')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // The one gate on every `/admin*` route (spec 13.1.2).
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(8));
    }
}
