<?php

namespace BuildAgents\AgentEngine\Concerns;

use BuildAgents\AgentEngine\AgentEngine;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adicione ao model "dono" dos agentes (Tenant, User, Company...).
 */
trait HasAgents
{
    public function agents(): MorphMany
    {
        return $this->morphMany(AgentEngine::model('agent'), 'owner');
    }

    public function agentApiKeys(): MorphMany
    {
        return $this->morphMany(AgentEngine::model('api_key'), 'owner');
    }
}
