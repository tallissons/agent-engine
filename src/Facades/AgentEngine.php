<?php

namespace BuildAgents\AgentEngine\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \BuildAgents\AgentEngine\Data\AgentResult run(\BuildAgents\AgentEngine\Models\Agent|string $agent, string $prompt, ?string $sessionId = null, ?\BuildAgents\AgentEngine\Data\RunOptions $options = null)
 * @method static \BuildAgents\AgentEngine\Models\Agent find(string $uuid)
 * @method static \BuildAgents\AgentEngine\Data\AgentResult handleWebhook(\BuildAgents\AgentEngine\Models\Agent $agent, array $data, string $body = '')
 * @method static string|null webhookUrl()
 *
 * @see \BuildAgents\AgentEngine\AgentEngine
 */
class AgentEngine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BuildAgents\AgentEngine\AgentEngine::class;
    }
}
