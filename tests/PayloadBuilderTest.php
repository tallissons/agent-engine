<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Models\AgentApiKey;
use BuildAgents\AgentEngine\Payload\AgentPayloadBuilder;

class PayloadBuilderTest extends TestCase
{
    public function test_uses_platform_defaults_and_suffix_without_own_api_key(): void
    {
        config(['agent-engine.instruction_suffix' => 'Assunto do Build Agents.']);

        $agent = $this->makeAgent(['temperature' => 0.7, 'response_schema' => ['type' => 'object']]);
        $agent->tools()->create(['tool_slug' => 'http', 'name' => 'Consulta', 'config' => ['url' => 'x']]);

        $payload = app(AgentPayloadBuilder::class)->build(
            $agent->fresh(), 'Olá', 'sess-tenant_1', new RunOptions(files: ['a.pdf'], externalReference: 'ref-1'), 'sync', null
        );

        $this->assertSame('atendimento_cliente', $payload['name']);
        $this->assertSame('gemini', $payload['provider']);
        $this->assertSame('platform-key', $payload['api_key']);
        $this->assertSame('gemini-2.5-flash', $payload['model']);
        $this->assertSame("Seja educado.\n\nAssunto do Build Agents.", $payload['instruction']);
        $this->assertSame(0.7, $payload['temperature']);
        $this->assertNull($payload['max_tokens']);
        $this->assertSame(['type' => 'object'], $payload['response_schema']);
        $this->assertSame([['tool_slug' => 'http', 'tool_name' => 'Consulta', 'config' => ['url' => 'x']]], $payload['tools']);
        $this->assertSame([], $payload['sub_agents']);
        $this->assertSame(['a.pdf'], $payload['files']);
        $this->assertSame('ref-1', $payload['external_reference']);
        $this->assertSame($agent->uuid, $payload['agent_id']);
    }

    public function test_uses_own_api_key_without_suffix(): void
    {
        config(['agent-engine.instruction_suffix' => 'Assunto do Build Agents.']);

        $key = AgentApiKey::create(['provider' => 'openai', 'api_key' => 'sk-own']);
        $agent = $this->makeAgent(['api_key_id' => $key->id, 'model_name' => 'gpt-4o']);

        $payload = app(AgentPayloadBuilder::class)->definition($agent->fresh());

        $this->assertSame('openai', $payload['provider']);
        $this->assertSame('sk-own', $payload['api_key']);
        $this->assertSame('gpt-4o', $payload['model']);
        $this->assertSame('Seja educado.', $payload['instruction']);
        $this->assertNotSame('sk-own', AgentApiKey::query()->toBase()->value('api_key'), 'api_key deve ser criptografada');
    }

    public function test_subagents_respect_max_depth_and_cycles(): void
    {
        $root = $this->makeAgent(['name' => 'Root']);
        $child = $this->makeAgent(['name' => 'Child']);
        $grandchild = $this->makeAgent(['name' => 'Grand Child']);

        $root->subagents()->attach($child->id);
        $child->subagents()->attach([$grandchild->id, $root->id]); // ciclo

        $builder = app(AgentPayloadBuilder::class);

        $payload = $builder->definition($root->fresh());
        $this->assertCount(1, $payload['sub_agents']);
        $this->assertSame('child', $payload['sub_agents'][0]['name']);
        $this->assertArrayNotHasKey('sub_agents', $payload['sub_agents'][0]);

        config(['agent-engine.subagents.max_depth' => 5]);

        $payload = $builder->definition($root->fresh());
        $nested = $payload['sub_agents'][0]['sub_agents'];
        $this->assertSame(['grand_child'], array_column($nested, 'name'));
    }
}
