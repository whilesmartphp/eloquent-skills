<?php

namespace Whilesmart\Skills;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Whilesmart\Skills\Authorization\ActorBindingAuthorizer;
use Whilesmart\Skills\Authorization\OwnerAccessSkillAuthorizer;
use Whilesmart\Skills\Contracts\BindingAuthorizer;
use Whilesmart\Skills\Contracts\ResponseFormatter;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Http\Formatters\DefaultResponseFormatter;
use Whilesmart\Skills\Imports\SkillSourceRegistry;
use Whilesmart\Skills\Services\SkillVersionResolver;

class SkillsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/skills.php', 'skills');

        $this->bindConfigured(SkillAuthorizer::class, 'skills.authorizer', OwnerAccessSkillAuthorizer::class);
        $this->bindConfigured(BindingAuthorizer::class, 'skills.binding_authorizer', ActorBindingAuthorizer::class);
        $this->bindConfigured(ResponseFormatter::class, 'skills.response_formatter', DefaultResponseFormatter::class);

        $this->app->singleton(SkillSourceRegistry::class);
        $this->app->singleton(SkillVersionResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/skills.php' => config_path('skills.php'),
        ], ['skills', 'skills-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['skills', 'skills-migrations']);

        $this->publishes([
            __DIR__.'/../routes/api.php' => base_path('routes/skills.php'),
        ], ['skills', 'skills-routes']);

        if (config('skills.register_routes', true)) {
            Route::middleware((array) config('skills.route_middleware', ['api', 'auth:sanctum']))
                ->prefix((string) config('skills.route_prefix', 'api'))
                ->name((string) config('skills.route_name_prefix', 'skills.'))
                ->group(__DIR__.'/../routes/api.php');
        }
    }

    private function bindConfigured(string $contract, string $key, string $default): void
    {
        $this->app->bindIf($contract, function (Application $app) use ($contract, $key, $default) {
            $class = config($key) ?: $default;

            if (! is_string($class) || ! is_a($class, $contract, true)) {
                throw new InvalidArgumentException("The [$key] setting must name a class implementing $contract.");
            }

            return $app->make($class);
        });
    }
}
