<?php

namespace BuildAgents\AgentEngine\Http\Controllers;

use BuildAgents\AgentEngine\AgentEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, AgentEngine $engine): JsonResponse
    {
        $expected = (string) config('agent-engine.webhook.secret');

        abort_if($expected === '' || ! hash_equals($expected, $secret), 403);

        $agentUuid = $request->input('agent_id');

        abort_unless(is_string($agentUuid) && $agentUuid !== '', 422, 'agent_id ausente.');

        $result = $engine->handleWebhook($engine->find($agentUuid), $request->all(), $request->getContent());

        return new JsonResponse(['received' => true, 'status' => $result->status]);
    }
}
