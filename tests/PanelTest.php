<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\Contracts\PanelOwnerResolver;
use BuildAgents\AgentEngine\Models\Agent;
use BuildAgents\AgentEngine\Models\AgentApiKey;
use BuildAgents\AgentEngine\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class PanelTest extends TestCase
{
    protected function allow(): void
    {
        Gate::define('viewAgentEngine', fn ($user = null) => true);
    }

    public function test_denied_without_gate_outside_local(): void
    {
        $this->get('/agent-engine/panel/agents')->assertForbidden();
    }

    public function test_gate_can_deny(): void
    {
        Gate::define('viewAgentEngine', fn ($user = null) => false);

        $this->get('/agent-engine/panel/agents')->assertForbidden();
    }

    public function test_index_lists_agents(): void
    {
        $this->allow();
        $this->makeAgent(['name' => 'Vendas']);

        $sub = $this->makeAgent(['name' => 'Sub']);
        Agent::where('name', 'Vendas')->first()->subagents()->attach($sub->id);

        $this->assertSame(1, Agent::withCount('subagents')->where('name', 'Vendas')->first()->subagents_count);
        $this->assertSame(['Vendas'], Agent::has('subagents')->pluck('name')->all());

        $this->get('/agent-engine/panel')->assertRedirect(route('agent-engine.panel.agents.index'));
        $this->get('/agent-engine/panel/agents')->assertOk()->assertSee('Vendas');
        $this->get('/agent-engine/panel/agents/create')->assertOk()->assertSee('name="name"', false);
    }

    public function test_create_agent_with_tools_and_subagents(): void
    {
        $this->allow();
        $sub1 = Agent::create(['name' => 'Sub Um']);
        $sub2 = Agent::create(['name' => 'Sub Dois']);

        $this->post('/agent-engine/panel/agents', [
            'name' => 'Principal',
            'instruction' => 'Ajude',
            'is_active' => '1',
            'temperature' => '0.5',
            'response_schema' => '{"type":"object"}',
            'tools' => [
                ['tool_slug' => 'http', 'name' => 'consulta', 'config' => '{"url":"https://x"}'],
                ['tool_slug' => 'calc', 'name' => 'calculadora', 'config' => ''],
            ],
            'subagents' => [$sub2->id, $sub1->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $agent = Agent::where('name', 'Principal')->firstOrFail();

        $this->assertSame(0.5, $agent->temperature);
        $this->assertSame(['type' => 'object'], $agent->response_schema);
        $this->assertSame(['url' => 'https://x'], $agent->tools->firstWhere('name', 'consulta')->config);
        $this->assertNull($agent->tools->firstWhere('name', 'calculadora')->config);
        $this->assertSame(['Sub Dois', 'Sub Um'], $agent->subagents->pluck('name')->all());

        $this->get("/agent-engine/panel/agents/{$agent->uuid}/edit")->assertOk()->assertSee('Principal');
    }

    public function test_update_replaces_tools_and_validates_json(): void
    {
        $this->allow();
        $agent = $this->makeAgent();
        $agent->tools()->create(['tool_slug' => 'old', 'name' => 'antiga']);

        $this->put("/agent-engine/panel/agents/{$agent->uuid}", ['name' => 'X', 'response_schema' => '{invalido'])
            ->assertSessionHasErrors('response_schema');

        $this->put("/agent-engine/panel/agents/{$agent->uuid}", [
            'name' => 'Renomeado',
            'tools' => [['tool_slug' => 'new', 'name' => 'nova']],
        ])->assertSessionHasNoErrors();

        $agent->refresh();
        $this->assertSame('Renomeado', $agent->name);
        $this->assertFalse($agent->is_active);
        $this->assertSame(['nova'], $agent->tools->pluck('name')->all());
    }

    public function test_agent_cannot_be_its_own_subagent(): void
    {
        $this->allow();
        $agent = $this->makeAgent();

        $this->put("/agent-engine/panel/agents/{$agent->uuid}", ['name' => 'X', 'subagents' => [$agent->id]])
            ->assertSessionHasErrors('subagents');
    }

    public function test_delete_soft_deletes(): void
    {
        $this->allow();
        $agent = $this->makeAgent();

        $this->delete("/agent-engine/panel/agents/{$agent->uuid}")->assertRedirect();

        $this->assertSoftDeleted($agent);
    }

    public function test_api_key_crud_keeps_key_when_blank(): void
    {
        $this->allow();

        $this->post('/agent-engine/panel/api-keys', ['provider' => 'openai', 'name' => 'Prod', 'api_key' => 'sk-123456'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $key = AgentApiKey::firstOrFail();
        $this->assertSame('sk-123456', $key->api_key);

        $this->get('/agent-engine/panel/api-keys')->assertOk()->assertSee('••••3456')->assertDontSee('sk-123456');

        $this->put("/agent-engine/panel/api-keys/{$key->id}", ['provider' => 'gemini', 'api_key' => ''])
            ->assertSessionHasNoErrors();

        $key->refresh();
        $this->assertSame('gemini', $key->provider);
        $this->assertSame('sk-123456', $key->api_key);
    }

    public function test_owner_resolver_scopes_panel(): void
    {
        $this->allow();
        $mine = Tenant::create(['name' => 'Meu']);
        $other = Tenant::create(['name' => 'Outro']);
        $myAgent = $this->makeAgent(['name' => 'Meu Agente'], $mine);
        $otherAgent = $this->makeAgent(['name' => 'Agente Alheio'], $other);
        $otherKey = AgentApiKey::create(['provider' => 'x', 'api_key' => 'k']);
        $otherKey->owner()->associate($other)->save();

        $this->app->bind(PanelOwnerResolver::class, fn () => new class($mine) implements PanelOwnerResolver
        {
            public function __construct(private Model $owner)
            {
            }

            public function resolve(Request $request): ?Model
            {
                return $this->owner;
            }
        });

        $this->get('/agent-engine/panel/agents')->assertSee('Meu Agente')->assertDontSee('Agente Alheio');
        $this->get("/agent-engine/panel/agents/{$otherAgent->uuid}/edit")->assertNotFound();
        $this->put("/agent-engine/panel/agents/{$myAgent->uuid}", ['name' => 'X', 'subagents' => [$otherAgent->id]])
            ->assertSessionHasErrors('subagents');
        $this->put("/agent-engine/panel/agents/{$myAgent->uuid}", ['name' => 'X', 'api_key_id' => $otherKey->id, 'model_name' => 'm'])
            ->assertSessionHasErrors('api_key_id');

        $this->post('/agent-engine/panel/agents', ['name' => 'Novo'])->assertSessionHasNoErrors();
        $this->assertTrue(Agent::where('name', 'Novo')->firstOrFail()->owner->is($mine));
    }

    public function test_playground(): void
    {
        $this->allow();
        $agent = $this->makeAgent();

        Http::fake(['engine.test/*' => Http::response([
            'session_id' => 'x', 'content' => 'Resposta', 'token_usage' => ['total_token_count' => 7],
        ])]);

        $this->get("/agent-engine/panel/agents/{$agent->uuid}/playground")->assertOk()->assertSee('aeChat', false);

        $this->postJson("/agent-engine/panel/agents/{$agent->uuid}/playground", ['message' => 'Oi'])
            ->assertOk()
            ->assertJson(['content' => 'Resposta', 'token_usage' => ['total_token_count' => 7]]);

        $this->postJson("/agent-engine/panel/agents/{$agent->uuid}/playground", [])->assertUnprocessable();
    }
}
