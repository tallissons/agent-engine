<?php

namespace BuildAgents\AgentEngine;

use BuildAgents\AgentEngine\Contracts\InstructionDecorator;
use BuildAgents\AgentEngine\Contracts\SessionResolver;
use BuildAgents\AgentEngine\Contracts\PanelOwnerResolver;
use BuildAgents\AgentEngine\Http\Controllers\AssetController;
use BuildAgents\AgentEngine\Http\Middleware\Authorize;
use BuildAgents\AgentEngine\Support\ConfigInstructionDecorator;
use BuildAgents\AgentEngine\Support\NullOwnerResolver;
use BuildAgents\AgentEngine\Support\OwnerSessionResolver;
use BuildAgents\AgentEngine\View\Components\Chat;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AgentEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/agent-engine.php', 'agent-engine');

        // Os projetos podem sobrescrever estes bindings no próprio AppServiceProvider.
        $this->app->bindIf(SessionResolver::class, OwnerSessionResolver::class);
        $this->app->bindIf(InstructionDecorator::class, ConfigInstructionDecorator::class);
        $this->app->bindIf(PanelOwnerResolver::class, fn ($app) => $app->make(
            config('agent-engine.panel.owner_resolver') ?: NullOwnerResolver::class
        ));

        $this->app->singleton(AgentEngine::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'agent-engine');

        if (config('agent-engine.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'agent-engine');
        Blade::component('agent-engine::chat', Chat::class);

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/agent-engine'),
            ], 'agent-engine-views');

            $this->publishes([
                __DIR__.'/../config/agent-engine.php' => config_path('agent-engine.php'),
            ], 'agent-engine-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'agent-engine-migrations');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/agent-engine'),
            ], 'agent-engine-lang');
        }
    }

    protected function registerRoutes(): void
    {
        if (config('agent-engine.webhook.enabled', true)) {
            Route::group([
                'prefix' => config('agent-engine.webhook.prefix', 'agent-engine'),
                'middleware' => config('agent-engine.webhook.middleware', ['api']),
            ], __DIR__.'/../routes/webhook.php');
        }

        if (config('agent-engine.panel.enabled', true)) {
            Route::group([
                'prefix' => config('agent-engine.panel.path', 'agent-engine/panel'),
                'middleware' => [...config('agent-engine.panel.middleware', ['web']), Authorize::class],
                'as' => 'agent-engine.panel.',
            ], __DIR__.'/../routes/panel.php');
        }

        if (config('agent-engine.widget.enabled', true)) {
            // por IP: a sessão não serve de chave, pois basta não enviar o cookie para ganhar outra
            RateLimiter::for('agent-engine-widget', fn (Request $request) => Limit::perMinute((int) config('agent-engine.widget.throttle', 30))
                ->by($request->ip()));

            Route::group([
                'prefix' => config('agent-engine.widget.path', 'agent-engine/chat'),
                'middleware' => [...config('agent-engine.widget.middleware', ['web']), 'throttle:agent-engine-widget'],
                'as' => 'agent-engine.widget.',
            ], __DIR__.'/../routes/widget.php');
        }

        if (config('agent-engine.panel.enabled', true) || config('agent-engine.widget.enabled', true)) {
            Route::get('agent-engine/assets/agent-engine.css', [AssetController::class, 'css'])
                ->name('agent-engine.assets.css');
        }
    }
}
