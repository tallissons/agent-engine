<?php

namespace BuildAgents\AgentEngine\Http\Controllers;

use BuildAgents\AgentEngine\Facades\AgentEngine;
use BuildAgents\AgentEngine\Http\Controllers\Concerns\RespondsWithChat;
use BuildAgents\AgentEngine\View\Components\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Endpoint do widget <x-agent-engine::chat />. Só aceita agentes vindos do
 * token criptografado gerado pelo componente.
 */
class WidgetController extends Controller
{
    use RespondsWithChat;

    public function send(Request $request): JsonResponse
    {
        $token = $this->token($request);

        return $this->chat(
            $request,
            AgentEngine::find($token['agent']),
            $this->sessionKey($token['agent']),
            $token['reference'] ?? '',
            // vem do token criptografado: o HTML do cliente não consegue ativar
            filesAllowed: (bool) ($token['files'] ?? false),
        );
    }

    public function reset(Request $request): JsonResponse
    {
        return $this->resetChat($request, $this->sessionKey($this->token($request)['agent']));
    }

    protected function token(Request $request): array
    {
        $token = Chat::decodeToken((string) $request->input('token'));

        abort_if($token === null, 403);

        return $token;
    }

    protected function sessionKey(string $uuid): string
    {
        return "agent-engine.widget.$uuid";
    }
}
