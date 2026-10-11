<?php

namespace BuildAgents\AgentEngine\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Libera o painel pelo Gate 'viewAgentEngine'.
 * Sem o Gate definido, o painel só abre no ambiente local.
 */
class Authorize
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = Gate::has('viewAgentEngine')
            ? Gate::allows('viewAgentEngine')
            : app()->environment('local');

        abort_unless($allowed, 403);

        return $next($request);
    }
}
