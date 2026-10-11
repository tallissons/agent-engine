<?php

namespace BuildAgents\AgentEngine\Support;

use BuildAgents\AgentEngine\Contracts\PanelOwnerResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class NullOwnerResolver implements PanelOwnerResolver
{
    public function resolve(Request $request): ?Model
    {
        return null;
    }
}
