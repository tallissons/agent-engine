<?php

namespace BuildAgents\AgentEngine\Http\Controllers\Panel;

use BuildAgents\AgentEngine\Http\Controllers\Concerns\RespondsWithChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaygroundController extends PanelController
{
    use RespondsWithChat;

    public function show(Request $request, string $agent): View
    {
        return view('agent-engine::playground', ['agent' => $this->findAgent($request, $agent)]);
    }

    public function send(Request $request, string $agent): JsonResponse
    {
        $agent = $this->findAgent($request, $agent);

        return $this->chat($request, $agent, $this->sessionKey($agent->uuid), 'playground', withTokenUsage: true);
    }

    public function reset(Request $request, string $agent): JsonResponse
    {
        return $this->resetChat($request, $this->sessionKey($this->findAgent($request, $agent)->uuid));
    }

    protected function sessionKey(string $uuid): string
    {
        return "agent-engine.playground.$uuid";
    }
}
