<?php

namespace BuildAgents\AgentEngine\Http\Controllers\Panel;

use BuildAgents\AgentEngine\AgentEngine;
use BuildAgents\AgentEngine\Http\Requests\ApiKeyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyController extends PanelController
{
    public function index(Request $request): View
    {
        return view('agent-engine::api-keys.index', [
            'apiKeys' => $this->apiKeys($request)->withCount('agents')->orderBy('provider')->get(),
        ]);
    }

    public function create(): View
    {
        $model = AgentEngine::model('api_key');

        return view('agent-engine::api-keys.form', ['apiKey' => new $model()]);
    }

    public function store(ApiKeyRequest $request): RedirectResponse
    {
        $model = AgentEngine::model('api_key');
        $apiKey = new $model($request->keyData());
        $this->assignOwner($apiKey, $request);
        $apiKey->save();

        return redirect()
            ->route('agent-engine.panel.api-keys.index')
            ->with('agent-engine.status', __('agent-engine::ui.saved'));
    }

    public function edit(Request $request, string $apiKey): View
    {
        return view('agent-engine::api-keys.form', ['apiKey' => $this->findApiKey($request, $apiKey)]);
    }

    public function update(ApiKeyRequest $request, string $apiKey): RedirectResponse
    {
        $this->findApiKey($request, $apiKey)->update($request->keyData());

        return redirect()
            ->route('agent-engine.panel.api-keys.index')
            ->with('agent-engine.status', __('agent-engine::ui.saved'));
    }

    public function destroy(Request $request, string $apiKey): RedirectResponse
    {
        $this->findApiKey($request, $apiKey)->delete();

        return redirect()
            ->route('agent-engine.panel.api-keys.index')
            ->with('agent-engine.status', __('agent-engine::ui.deleted'));
    }
}
