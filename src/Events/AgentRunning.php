<?php

namespace BuildAgents\AgentEngine\Events;

use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Models\Agent;

/**
 * Disparado antes da chamada ao serviço.
 * Listeners podem lançar AgentRunBlocked (limites de plano, créditos...)
 * ou alterar $payload.
 */
class AgentRunning
{
    public function __construct(
        public readonly Agent $agent,
        public readonly string $prompt,
        public readonly string $sessionId,
        public readonly RunOptions $options,
        public array $payload,
    ) {
    }
}
