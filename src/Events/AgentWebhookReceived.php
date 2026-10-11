<?php

namespace BuildAgents\AgentEngine\Events;

use BuildAgents\AgentEngine\Models\Agent;

/**
 * Disparado ao receber o retorno assíncrono do serviço, antes de AgentCompleted/AgentFailed.
 */
class AgentWebhookReceived
{
    public function __construct(
        public readonly Agent $agent,
        public readonly array $payload,
    ) {
    }
}
