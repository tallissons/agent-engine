<?php

namespace BuildAgents\AgentEngine\Payload;

use BuildAgents\AgentEngine\Contracts\InstructionDecorator;
use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Models\Agent;
use BuildAgents\AgentEngine\Models\AgentTool;
use BuildAgents\AgentEngine\Support\PdfRasterizer;
use Illuminate\Support\Str;

class AgentPayloadBuilder
{
    public function __construct(
        protected InstructionDecorator $instructionDecorator,
        protected PdfRasterizer $rasterizer,
    ) {
    }

    /**
     * Monta o corpo do POST enviado ao serviço de agentes.
     */
    public function build(
        Agent $agent,
        string $prompt,
        string $engineSessionId,
        RunOptions $options,
        string $responseMode,
        ?string $webhookUrl,
    ): array {
        return array_merge($this->definition($agent), [
            'prompt' => $prompt,
            'session_id' => $engineSessionId,
            'files' => $options->rasterizePdf ? $this->rasterizer->rasterize($options->files) : $options->files,
            'agent_id' => $agent->uuid,
            'external_reference' => $options->externalReference,
            'response_mode' => $responseMode,
            'webhook_url' => $webhookUrl,
        ], $options->extra);
    }

    /**
     * Configuração do agente (e de seus subagentes) no formato do serviço.
     *
     * @param  array<int, int>  $visited  ids já incluídos no caminho atual (evita ciclos).
     */
    public function definition(Agent $agent, int $depth = 0, array $visited = []): array
    {
        $isRoot = $depth === 0;
        $visited[] = $agent->getKey();
        $apiKey = $agent->apiKey;

        $data = [
            'name' => Str::snake($agent->name),
            'provider' => $apiKey ? $apiKey->provider : config('agent-engine.default_provider'),
            'api_key' => $apiKey ? $apiKey->api_key : config('agent-engine.default_api_key'),
            'model' => $apiKey ? $agent->model_name : config('agent-engine.default_model'),
            'instruction' => $this->instructionDecorator->decorate($agent, $agent->instruction, $isRoot),
            'description' => $agent->description,
            'temperature' => $agent->temperature,
            'max_tokens' => $agent->max_tokens,
            'top_p' => $agent->top_p,
            'top_k' => $agent->top_k,
            'response_schema' => $agent->response_schema,
            'tools' => $agent->tools->map(fn (AgentTool $tool) => [
                'tool_slug' => $tool->tool_slug,
                'tool_name' => $tool->name,
                'config' => $tool->config,
            ])->values()->all(),
        ];

        if ($depth >= (int) config('agent-engine.subagents.max_depth', 1)) {
            return $data;
        }

        $subagents = $agent->subagents
            ->reject(fn (Agent $sub) => in_array($sub->getKey(), $visited, true))
            ->map(fn (Agent $sub) => $this->definition($sub, $depth + 1, $visited))
            ->values()
            ->all();

        if ($isRoot || $subagents !== []) {
            $data['sub_agents'] = $subagents;
        }

        return $data;
    }
}
