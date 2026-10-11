<?php

namespace BuildAgents\AgentEngine\Events;

use BuildAgents\AgentEngine\Data\AgentResult;
use BuildAgents\AgentEngine\Models\Agent;

class AgentFailed
{
    public function __construct(
        public readonly Agent $agent,
        public readonly AgentResult $result,
        public readonly bool $viaWebhook = false,
    ) {
    }
}
