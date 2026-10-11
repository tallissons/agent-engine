<?php

namespace BuildAgents\AgentEngine\Events;

use BuildAgents\AgentEngine\Data\AgentResult;
use BuildAgents\AgentEngine\Models\Agent;

/**
 * Disparado quando há resposta com conteúdo, síncrona ou via webhook.
 * Use para registrar consumo de tokens.
 */
class AgentCompleted
{
    public function __construct(
        public readonly Agent $agent,
        public readonly AgentResult $result,
        public readonly bool $viaWebhook = false,
    ) {
    }
}
