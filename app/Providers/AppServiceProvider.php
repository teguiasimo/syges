<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Configure les règles globales d'habilitation et privilèges Gate/Spatie.
     * Le Super Administrateur dispose implicitement de l'intégralité des permissions du système.
     * Prise en charge native du OU logique par tube ('|') pour les middlewares et contrôles d'habilitation.
     */
    protected function configureGates(): void
    {
        Gate::before(function ($user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Prise en charge universelle du OU logique (ex: 'personnels.delete|personnels.manage')
            if (str_contains($ability, '|')) {
                foreach (explode('|', $ability) as $subAbility) {
                    $trimmed = trim($subAbility);
                    if ($trimmed !== '' && $user->can($trimmed)) {
                        return true;
                    }
                }
            }

            return null;
        });
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

        // Force automatiquement le HTTPS et les cookies sécurisés lors de la mise en ligne en production
        // dès que APP_URL est en https:// ou que FORCE_HTTPS=true est activé dans le fichier .env
        if (
            $this->app->isProduction()
            && (
                filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)
                || str_starts_with((string) config('app.url'), 'https://')
            )
        ) {
            URL::forceScheme('https');
            config(['session.secure' => true]);
        }

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
