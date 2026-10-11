<?php

namespace BuildAgents\AgentEngine\Support;

use BuildAgents\AgentEngine\Contracts\SessionResolver;
use BuildAgents\AgentEngine\Models\Agent;
use Illuminate\Support\Str;

/**
 * Adiciona o sufixo "-{dono}_{id}" ao session_id (ex.: "abc-tenant_5").
 * Agentes sem dono usam o session_id sem alteração.
 */
class OwnerSessionResolver implements SessionResolver
{
    public function toEngine(Agent $agent, string $sessionId): string
    {
        return $sessionId.$this->suffix($agent);
    }

    public function fromEngine(Agent $agent, ?string $engineSessionId): ?string
    {
        $suffix = $this->suffix($agent);

        if ($engineSessionId === null || $suffix === '' || ! str_ends_with($engineSessionId, $suffix)) {
            return $engineSessionId;
        }

        return substr($engineSessionId, 0, -strlen($suffix));
    }

    protected function suffix(Agent $agent): string
    {
        if (empty($agent->owner_type) || empty($agent->owner_id)) {
            return '';
        }

        return '-'.Str::snake(class_basename($agent->owner_type)).'_'.$agent->owner_id;
    }
}
