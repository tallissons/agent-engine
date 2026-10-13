<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Events\AgentCompleted;
use BuildAgents\AgentEngine\Events\AgentFailed;
use BuildAgents\AgentEngine\Events\AgentRunning;
use BuildAgents\AgentEngine\Exceptions\AgentEngineException;
use BuildAgents\AgentEngine\Exceptions\AgentRunBlocked;
use BuildAgents\AgentEngine\Facades\AgentEngine;
use BuildAgents\AgentEngine\Jobs\ForwardToAgentWebhook;
use BuildAgents\AgentEngine\Models\Agent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class RunAgentTest extends TestCase
{
    public function test_sync_response(): void
    {
        Event::fake([AgentCompleted::class]);
        $agent = $this->makeAgent();
        $suffix = '-tenant_'.$agent->owner_id;

        Http::fake(['engine.test/*' => Http::response([
            'session_id' => 'abc'.$suffix,
            'content' => 'Oi!',
            'token_usage' => ['prompt_token_count' => 3, 'candidates_token_count' => 2, 'total_token_count' => 5],
        ])]);

        $result = AgentEngine::run($agent->uuid, 'Olá', 'abc');

        $this->assertTrue($result->successful());
        $this->assertSame('abc', $result->sessionId);
        $this->assertSame('Oi!', $result->content);
        $this->assertSame(5, $result->totalTokens());
        $this->assertSame(['session_id' => 'abc', 'content' => 'Oi!'], $result->toArray());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://engine.test/agente'
            && $request->hasHeader('X-Token', 'token-123')
            && $request['session_id'] === 'abc'.$suffix
            && $request['response_mode'] === 'sync'
            && $request['webhook_url'] !== null);

        Event::assertDispatched(AgentCompleted::class, fn (AgentCompleted $e) => $e->result === $result && ! $e->viaWebhook);
    }

    public function test_agent_without_owner(): void
    {
        $agent = Agent::create(['name' => 'Sem Dono', 'instruction' => 'Ajude.']);

        Http::fake(['engine.test/*' => Http::response(['session_id' => 'abc', 'content' => 'Oi!'])]);

        $result = AgentEngine::run($agent, 'Olá', 'abc');

        $this->assertNull($agent->owner);
        $this->assertSame('abc', $result->sessionId);
        Http::assertSent(fn (Request $request) => $request['session_id'] === 'abc');
    }

    public function test_webhook_mode_returns_processing(): void
    {
        $agent = $this->makeAgent(['webhook_url' => 'https://client.test/hook']);

        Http::fake(['engine.test/*' => Http::response([
            'status' => 'processing',
            'session_id' => 'abc-tenant_'.$agent->owner_id,
            'external_reference' => 'ref-9',
        ])]);

        $result = AgentEngine::run($agent, 'Olá', 'abc', new RunOptions(externalReference: 'ref-9'));

        $this->assertTrue($result->isProcessing());
        $this->assertSame([
            'agent_id' => $agent->uuid,
            'external_reference' => 'ref-9',
            'session_id' => 'abc',
            'status' => 'processing',
        ], $result->toArray());

        Http::assertSent(fn (Request $request) => $request['response_mode'] === 'webhook'
            && $request['webhook_url'] === 'http://localhost/agent-engine/webhook/webhook-secret');
    }

    public function test_async_option_uses_webhook_without_agent_webhook_url(): void
    {
        $agent = $this->makeAgent();

        Http::fake(['engine.test/*' => Http::response([
            'status' => 'processing',
            'session_id' => 'abc-tenant_'.$agent->owner_id,
        ])]);

        $result = AgentEngine::run($agent, 'Olá', 'abc', new RunOptions(async: true));

        $this->assertTrue($result->isProcessing());
        Http::assertSent(fn (Request $request) => $request['response_mode'] === 'webhook'
            && $request['webhook_url'] === 'http://localhost/agent-engine/webhook/webhook-secret');
    }

    public function test_async_option_throws_when_webhook_unavailable(): void
    {
        config(['agent-engine.webhook.secret' => null]);
        Http::fake();
        $agent = $this->makeAgent();

        $this->expectException(AgentEngineException::class);

        try {
            AgentEngine::run($agent, 'Olá', 'abc', new RunOptions(async: true));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_sync_and_async_together_throws(): void
    {
        Http::fake();
        $agent = $this->makeAgent();

        $this->expectException(AgentEngineException::class);

        try {
            AgentEngine::run($agent, 'Olá', 'abc', new RunOptions(sync: true, async: true));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_sync_option_forces_sync_and_forwards_to_agent_webhook(): void
    {
        config(['agent-engine.webhook.forward' => true]);
        Bus::fake([ForwardToAgentWebhook::class]);
        $agent = $this->makeAgent(['webhook_url' => 'https://client.test/hook']);

        Http::fake(['engine.test/*' => Http::response(['session_id' => 'x', 'content' => 'ok'])]);

        AgentEngine::run($agent, 'Olá', 'x', new RunOptions(sync: true));

        Http::assertSent(fn (Request $request) => $request['response_mode'] === 'sync');
        Bus::assertDispatched(ForwardToAgentWebhook::class, fn ($job) => $job->url === 'https://client.test/hook'
            && $job->data['content'] === 'ok');
    }

    #[DataProvider('errorProvider')]
    public function test_maps_engine_errors(mixed $body, string $code): void
    {
        Event::fake([AgentFailed::class]);
        Http::fake(['engine.test/*' => Http::response($body, 500)]);

        $result = AgentEngine::run($this->makeAgent(), 'Olá');

        $this->assertTrue($result->failed());
        $this->assertSame($code, $result->errorCode);
        $this->assertSame(trans("agent-engine::errors.$code"), $result->error);
        $this->assertStringStartsWith('Erro:', $result->error);
        Event::assertDispatched(AgentFailed::class);
    }

    public static function errorProvider(): array
    {
        return [
            'json com detail' => [['detail' => '400 API key not valid. Please pass a valid API key.'], 'invalid_api_key'],
            'string json' => ['"The model is overloaded."', 'model_overloaded'],
            'quota' => [['error' => ['message' => 'You exceeded your current quota']], 'quota_exceeded'],
            'desconhecido' => [['detail' => 'boom'], 'generic'],
        ];
    }

    public function test_connection_failure_returns_generic_error(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $result = AgentEngine::run($this->makeAgent(), 'Olá');

        $this->assertSame('generic', $result->errorCode);
    }

    public function test_listener_can_block_run(): void
    {
        Http::fake();
        Event::listen(AgentRunning::class, fn () => throw new AgentRunBlocked('Limite de créditos atingido para o plano atual'));

        try {
            AgentEngine::run($this->makeAgent(), 'Olá');
            $this->fail('Deveria ter bloqueado.');
        } catch (AgentRunBlocked $e) {
            $this->assertSame(403, $e->render()->getStatusCode());
        }

        Http::assertNothingSent();
    }

    public function test_listener_can_change_payload(): void
    {
        Http::fake(['engine.test/*' => Http::response(['content' => 'ok'])]);
        Event::listen(AgentRunning::class, fn (AgentRunning $e) => $e->payload['prompt'] = 'alterado');

        AgentEngine::run($this->makeAgent(), 'Olá');

        Http::assertSent(fn (Request $request) => $request['prompt'] === 'alterado');
    }

    public function test_inactive_agent_is_blocked(): void
    {
        $this->expectException(AgentRunBlocked::class);

        AgentEngine::run($this->makeAgent(['is_active' => false]), 'Olá');
    }
}
