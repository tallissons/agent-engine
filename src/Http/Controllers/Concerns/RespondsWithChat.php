<?php

namespace BuildAgents\AgentEngine\Http\Controllers\Concerns;

use BuildAgents\AgentEngine\Data\AgentResult;
use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Facades\AgentEngine;
use BuildAgents\AgentEngine\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Executa uma mensagem de chat em modo sync, guardando o session_id na sessão Laravel.
 */
trait RespondsWithChat
{
    protected function chat(Request $request, Agent $agent, string $sessionKey, string $externalReference = '', bool $withTokenUsage = false): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.(int) config('agent-engine.widget.max_length', 4000)],
        ]);

        $sessionId = $request->session()->get($sessionKey) ?? (string) Str::uuid();
        $request->session()->put($sessionKey, $sessionId);

        $result = AgentEngine::run($agent, $data['message'], $sessionId, new RunOptions(
            externalReference: $externalReference,
            sync: true,
        ));

        return $this->chatResponse($result, $withTokenUsage);
    }

    protected function resetChat(Request $request, string $sessionKey): JsonResponse
    {
        $request->session()->forget($sessionKey);

        return new JsonResponse(['reset' => true]);
    }

    protected function chatResponse(AgentResult $result, bool $withTokenUsage): JsonResponse
    {
        if ($result->failed()) {
            return new JsonResponse(['error' => $result->error], 422);
        }

        return new JsonResponse([
            'content' => $result->content,
            'token_usage' => $withTokenUsage ? $result->tokenUsage : null,
        ]);
    }
}
