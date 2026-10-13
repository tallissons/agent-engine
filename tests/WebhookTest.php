<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\Events\AgentCompleted;
use BuildAgents\AgentEngine\Events\AgentWebhookReceived;
use BuildAgents\AgentEngine\Jobs\ForwardToAgentWebhook;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class WebhookTest extends TestCase
{
    public function test_rejects_invalid_secret(): void
    {
        $agent = $this->makeAgent();

        $this->postJson('/agent-engine/webhook/wrong', ['agent_id' => $agent->uuid, 'content' => 'x'])
            ->assertForbidden();
    }

    public function test_unknown_agent_returns_404(): void
    {
        $this->postJson('/agent-engine/webhook/webhook-secret', ['agent_id' => 'nao-existe', 'content' => 'x'])
            ->assertNotFound();
    }

    public function test_does_not_forward_by_default(): void
    {
        Event::fake([AgentCompleted::class]);
        Bus::fake([ForwardToAgentWebhook::class]);

        $agent = $this->makeAgent(['webhook_url' => 'https://client.test/hook']);

        $this->postJson('/agent-engine/webhook/webhook-secret', ['agent_id' => $agent->uuid, 'content' => 'Pronto'])
            ->assertOk();

        Event::assertDispatched(AgentCompleted::class, fn (AgentCompleted $e) => $e->viaWebhook);
        Bus::assertNotDispatched(ForwardToAgentWebhook::class);
    }

    public function test_handles_async_result_and_forwards(): void
    {
        config(['agent-engine.webhook.forward' => true]);
        Event::fake([AgentCompleted::class, AgentWebhookReceived::class]);
        Bus::fake([ForwardToAgentWebhook::class]);

        $agent = $this->makeAgent(['webhook_url' => 'https://client.test/hook']);

        $this->postJson('/agent-engine/webhook/webhook-secret', [
            'agent_id' => $agent->uuid,
            'session_id' => 'abc-tenant_'.$agent->owner_id,
            'external_reference' => 'ref-1',
            'content' => 'Pronto',
            'token_usage' => ['prompt_token_count' => 1, 'candidates_token_count' => 1, 'total_token_count' => 2],
        ])->assertOk()->assertJson(['received' => true, 'status' => 'completed']);

        Event::assertDispatched(AgentWebhookReceived::class);
        Event::assertDispatched(AgentCompleted::class, fn (AgentCompleted $e) => $e->viaWebhook && $e->result->totalTokens() === 2);
        Bus::assertDispatched(ForwardToAgentWebhook::class, fn ($job) => $job->data === [
            'session_id' => 'abc',
            'external_reference' => 'ref-1',
            'content' => 'Pronto',
        ]);
    }

    public function test_forward_job_posts_to_url(): void
    {
        Http::fake();

        (new ForwardToAgentWebhook('https://client.test/hook', ['content' => 'x']))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://client.test/hook' && $request['content'] === 'x');
    }
}
