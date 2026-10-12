<?php

namespace BuildAgents\AgentEngine\Http\Controllers\Concerns;

use BuildAgents\AgentEngine\Data\AgentResult;
use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Facades\AgentEngine;
use BuildAgents\AgentEngine\Models\Agent;
use BuildAgents\AgentEngine\Support\FileEncoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Executa uma mensagem de chat em modo sync, guardando o session_id na sessão Laravel.
 */
trait RespondsWithChat
{
    /**
     * @param  bool  $filesAllowed  se o contexto (playground/widget) permite anexos; o agente também precisa de allow_files.
     */
    protected function chat(
        Request $request,
        Agent $agent,
        string $sessionKey,
        string $externalReference = '',
        bool $withTokenUsage = false,
        bool $filesAllowed = false,
    ): JsonResponse {
        $data = $request->validate([
            'message' => ['nullable', 'required_without:files', 'string', 'max:'.(int) config('agent-engine.widget.max_length', 4000)],
            'files' => ['array', 'max:'.(int) config('agent-engine.files.max_files', 5)],
            'files.*' => [
                'file',
                'max:'.(int) config('agent-engine.files.max_size_kb', 10240),
                'mimes:'.implode(',', config('agent-engine.files.mimes', [])),
            ],
        ]);

        $uploads = $request->file('files', []);

        if ($uploads && (! $filesAllowed || ! $agent->allow_files)) {
            return new JsonResponse(['error' => __('agent-engine::ui.files_not_allowed')], 422);
        }

        $sessionId = $request->session()->get($sessionKey) ?? (string) Str::uuid();
        $request->session()->put($sessionKey, $sessionId);

        $result = AgentEngine::run($agent, (string) ($data['message'] ?? ''), $sessionId, new RunOptions(
            files: FileEncoder::encode($uploads),
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
