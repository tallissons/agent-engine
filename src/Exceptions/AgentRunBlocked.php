<?php

namespace BuildAgents\AgentEngine\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Lance em um listener de AgentRunning para impedir a execução.
 * Renderizada como HTTP 403 com {"error": mensagem}.
 */
class AgentRunBlocked extends AgentEngineException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['error' => $this->getMessage()], 403);
    }
}
