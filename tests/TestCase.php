<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\AgentEngineServiceProvider;
use BuildAgents\AgentEngine\Models\Agent;
use BuildAgents\AgentEngine\Tests\Fixtures\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [AgentEngineServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.locale', 'pt_BR');
        $app['config']->set('agent-engine.url', 'https://engine.test');
        $app['config']->set('agent-engine.access_token', 'token-123');
        $app['config']->set('agent-engine.default_provider', 'gemini');
        $app['config']->set('agent-engine.default_api_key', 'platform-key');
        $app['config']->set('agent-engine.default_model', 'gemini-2.5-flash');
        $app['config']->set('agent-engine.webhook.secret', 'webhook-secret');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function makeAgent(array $attributes = [], ?Tenant $tenant = null): Agent
    {
        $tenant ??= Tenant::create(['name' => 'Acme']);

        return $tenant->agents()->create(array_merge([
            'name' => 'Atendimento Cliente',
            'description' => 'Responde clientes',
            'instruction' => 'Seja educado.',
        ], $attributes));
    }
}
