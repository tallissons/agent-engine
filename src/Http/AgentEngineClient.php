<?php

namespace BuildAgents\AgentEngine\Http;

use BuildAgents\AgentEngine\Exceptions\AgentEngineException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class AgentEngineClient
{
    public function __construct(
        protected Factory $http,
    ) {
    }

    public function send(array $payload): Response
    {
        $url = config('agent-engine.url');

        if (empty($url)) {
            throw new AgentEngineException('agent-engine.url não configurada (AGENT_ENGINE_URL).');
        }

        return $this->http
            ->withHeaders(['X-Token' => (string) config('agent-engine.access_token')])
            ->acceptJson()
            ->timeout((int) config('agent-engine.timeout', 120))
            ->post(rtrim($url, '/').config('agent-engine.endpoint', '/agente'), $payload);
    }
}
