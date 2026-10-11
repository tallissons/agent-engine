<?php

namespace BuildAgents\AgentEngine;

use BuildAgents\AgentEngine\Contracts\SessionResolver;
use BuildAgents\AgentEngine\Data\AgentResult;
use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Events\AgentCompleted;
use BuildAgents\AgentEngine\Events\AgentFailed;
use BuildAgents\AgentEngine\Events\AgentRunning;
use BuildAgents\AgentEngine\Events\AgentWebhookReceived;
use BuildAgents\AgentEngine\Exceptions\AgentEngineException;
use BuildAgents\AgentEngine\Exceptions\AgentRunBlocked;
use BuildAgents\AgentEngine\Http\AgentEngineClient;
use BuildAgents\AgentEngine\Jobs\ForwardToAgentWebhook;
use BuildAgents\AgentEngine\Models\Agent;
use BuildAgents\AgentEngine\Payload\AgentPayloadBuilder;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AgentEngine
{
    /**
     * Trechos da resposta de erro do serviço => chave de tradução.
     */
    protected const ERROR_PATTERNS = [
        'API key not valid' => 'invalid_api_key',
        'not supported for generateContent' => 'model_not_found',
        'Function calling is not enabled' => 'model_not_supported',
        'You exceeded your current quota' => 'quota_exceeded',
        'The model is overloaded' => 'model_overloaded',
    ];

    public function __construct(
        protected AgentEngineClient $client,
        protected AgentPayloadBuilder $builder,
        protected SessionResolver $sessions,
        protected Dispatcher $events,
    ) {
    }

    /**
     * Classe de model configurada (agent, api_key, tool).
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function model(string $key): string
    {
        return config("agent-engine.models.$key") ?? match ($key) {
            'agent' => Models\Agent::class,
            'api_key' => Models\AgentApiKey::class,
            'tool' => Models\AgentTool::class,
        };
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(string $uuid): Agent
    {
        return static::model('agent')::query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Executa o agente no serviço.
     *
     * @throws AgentRunBlocked quando o agente está inativo ou um listener de AgentRunning bloqueia.
     */
    public function run(Agent|string $agent, string $prompt, ?string $sessionId = null, ?RunOptions $options = null): AgentResult
    {
        $agent = is_string($agent) ? $this->find($agent) : $agent;
        $agent->loadMissing(['apiKey', 'tools', 'subagents.apiKey', 'subagents.tools']);
        $sessionId ??= (string) Str::uuid();
        $options ??= new RunOptions();

        if (! $agent->is_active) {
            throw new AgentRunBlocked('Agente inativo.');
        }

        $webhookUrl = $this->webhookUrl();
        $responseMode = ($options->sync || empty($agent->webhook_url) || $webhookUrl === null) ? 'sync' : 'webhook';

        $payload = $this->builder->build(
            $agent,
            $prompt,
            $this->sessions->toEngine($agent, $sessionId),
            $options,
            $responseMode,
            $webhookUrl,
        );

        $event = new AgentRunning($agent, $prompt, $sessionId, $options, $payload);
        $this->events->dispatch($event);

        try {
            $response = $this->client->send($event->payload);
            $json = $response->json();
            $result = $this->resultFrom($agent, is_array($json) ? $json : [], $response->body());
        } catch (ConnectionException|AgentEngineException $e) {
            report($e);
            $result = $this->failure($agent, 'generic', ['exception' => $e->getMessage()]);
        }

        $this->dispatchResult($agent, $result, viaWebhook: false);

        return $result;
    }

    /**
     * Trata o retorno assíncrono recebido pelo webhook.
     */
    public function handleWebhook(Agent $agent, array $data, string $body = ''): AgentResult
    {
        $this->events->dispatch(new AgentWebhookReceived($agent, $data));

        $result = $this->resultFrom($agent, $data, $body);

        $this->dispatchResult($agent, $result, viaWebhook: true);

        return $result;
    }

    /**
     * Converte a resposta do serviço em AgentResult.
     */
    public function resultFrom(Agent $agent, array $data, string $body = ''): AgentResult
    {
        $sessionId = $this->sessions->fromEngine($agent, $data['session_id'] ?? null);

        if (array_key_exists('content', $data)) {
            return new AgentResult(
                status: AgentResult::COMPLETED,
                agentUuid: $agent->uuid,
                sessionId: $sessionId,
                content: $data['content'],
                tokenUsage: $data['token_usage'] ?? null,
                externalReference: $data['external_reference'] ?? null,
                raw: $data,
            );
        }

        if (($data['status'] ?? null) === 'processing') {
            return new AgentResult(
                status: AgentResult::PROCESSING,
                agentUuid: $agent->uuid,
                sessionId: $sessionId,
                externalReference: $data['external_reference'] ?? null,
                raw: $data,
            );
        }

        $haystack = $body !== '' ? $body : (string) json_encode($data);

        foreach (self::ERROR_PATTERNS as $needle => $code) {
            if (str_contains($haystack, $needle)) {
                return $this->failure($agent, $code, $data, $sessionId);
            }
        }

        return $this->failure($agent, 'generic', $data, $sessionId);
    }

    /**
     * URL que o serviço chama no modo assíncrono, ou null se o webhook estiver desativado.
     */
    public function webhookUrl(): ?string
    {
        $secret = config('agent-engine.webhook.secret');
        $routeName = config('agent-engine.webhook.route_name');

        if (! config('agent-engine.webhook.enabled') || empty($secret) || ! Route::has($routeName)) {
            return null;
        }

        return route($routeName, ['secret' => $secret]);
    }

    protected function failure(Agent $agent, string $code, array $raw = [], ?string $sessionId = null): AgentResult
    {
        return new AgentResult(
            status: AgentResult::FAILED,
            agentUuid: $agent->uuid,
            sessionId: $sessionId,
            externalReference: $raw['external_reference'] ?? null,
            error: trans("agent-engine::errors.$code"),
            errorCode: $code,
            raw: $raw,
        );
    }

    protected function dispatchResult(Agent $agent, AgentResult $result, bool $viaWebhook): void
    {
        if ($result->successful()) {
            $this->events->dispatch(new AgentCompleted($agent, $result, $viaWebhook));
        } elseif ($result->failed()) {
            $this->events->dispatch(new AgentFailed($agent, $result, $viaWebhook));
        }

        // Respostas síncronas com conteúdo e todos os retornos do webhook
        // são repassados ao webhook_url do agente.
        if ($agent->webhook_url && ($result->successful() || $viaWebhook)) {
            ForwardToAgentWebhook::dispatch($agent->webhook_url, $result->toArray());
        }
    }
}
