<?php

namespace BuildAgents\AgentEngine\Support;

use BuildAgents\AgentEngine\Contracts\InstructionDecorator;
use BuildAgents\AgentEngine\Models\Agent;

/**
 * Anexa config('agent-engine.instruction_suffix') à instrução do agente principal
 * quando ele usa a chave da plataforma.
 */
class ConfigInstructionDecorator implements InstructionDecorator
{
    public function decorate(Agent $agent, ?string $instruction, bool $isRoot): ?string
    {
        $suffix = config('agent-engine.instruction_suffix');

        if (! $isRoot || empty($suffix) || $agent->usesOwnApiKey()) {
            return $instruction;
        }

        return $instruction."\n\n".$suffix;
    }
}
