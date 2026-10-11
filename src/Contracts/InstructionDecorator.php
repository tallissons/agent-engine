<?php

namespace BuildAgents\AgentEngine\Contracts;

use BuildAgents\AgentEngine\Models\Agent;

/**
 * Permite alterar a instrução enviada ao serviço (ex.: anexar regras da plataforma).
 */
interface InstructionDecorator
{
    /**
     * @param  bool  $isRoot  true para o agente principal, false para subagentes.
     */
    public function decorate(Agent $agent, ?string $instruction, bool $isRoot): ?string;
}
