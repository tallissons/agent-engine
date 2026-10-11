<?php

namespace BuildAgents\AgentEngine\Http\Controllers\Panel;

use BuildAgents\AgentEngine\AgentEngine;
use BuildAgents\AgentEngine\Contracts\PanelOwnerResolver;
use BuildAgents\AgentEngine\Models\Agent;
use BuildAgents\AgentEngine\Models\AgentApiKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Base dos controllers do painel: aplica o escopo do dono (PanelOwnerResolver).
 */
abstract class PanelController extends Controller
{
    protected function owner(Request $request): ?Model
    {
        return app(PanelOwnerResolver::class)->resolve($request);
    }

    protected function agents(Request $request): Builder
    {
        return $this->scoped(AgentEngine::model('agent')::query(), $request);
    }

    protected function apiKeys(Request $request): Builder
    {
        return $this->scoped(AgentEngine::model('api_key')::query(), $request);
    }

    protected function findAgent(Request $request, string $uuid): Agent
    {
        return $this->agents($request)->where('uuid', $uuid)->firstOrFail();
    }

    protected function findApiKey(Request $request, int|string $id): AgentApiKey
    {
        return $this->apiKeys($request)->whereKey($id)->firstOrFail();
    }

    /**
     * Associa o dono atual a um model novo.
     */
    protected function assignOwner(Model $model, Request $request): void
    {
        if ($owner = $this->owner($request)) {
            $model->owner()->associate($owner);
        }
    }

    private function scoped(Builder $query, Request $request): Builder
    {
        $owner = $this->owner($request);

        if ($owner) {
            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        }

        return $query;
    }
}
