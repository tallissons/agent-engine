<?php

namespace BuildAgents\AgentEngine\Contracts;

use BuildAgents\AgentEngine\Models\Agent;

/**
 * Converte o session_id da aplicação para o enviado ao serviço e vice-versa.
 * Permite isolar sessões por dono (tenant, usuário...) sem expor isso ao cliente.
 */
interface SessionResolver
{
    public function toEngine(Agent $agent, string $sessionId): string;

    public function fromEngine(Agent $agent, ?string $engineSessionId): ?string;
}
