<?php

namespace BuildAgents\AgentEngine\Http\Controllers\Panel;

use BuildAgents\AgentEngine\AgentEngine;
use BuildAgents\AgentEngine\Http\Requests\AgentRequest;
use BuildAgents\AgentEngine\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AgentController extends PanelController
{
    public function index(Request $request): View
    {
        $agents = $this->agents($request)
            ->withCount(['tools', 'subagents'])
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->input('q').'%'))
            ->orderBy('name')
            ->simplePaginate(20)
            ->withQueryString();

        return view('agent-engine::agents.index', ['agents' => $agents]);
    }

    public function create(Request $request): View
    {
        $model = AgentEngine::model('agent');

        return $this->form($request, new $model(['is_active' => true]));
    }

    public function store(AgentRequest $request): RedirectResponse
    {
        $model = AgentEngine::model('agent');
        $agent = new $model();
        $this->assignOwner($agent, $request);

        $this->save($request, $agent);

        return redirect()
            ->route('agent-engine.panel.agents.edit', $agent->uuid)
            ->with('agent-engine.status', __('agent-engine::ui.saved'));
    }

    public function edit(Request $request, string $agent): View
    {
        return $this->form($request, $this->findAgent($request, $agent)->load(['tools', 'subagents']));
    }

    public function update(AgentRequest $request, string $agent): RedirectResponse
    {
        $agent = $this->findAgent($request, $agent);

        $this->save($request, $agent);

        return redirect()
            ->route('agent-engine.panel.agents.edit', $agent->uuid)
            ->with('agent-engine.status', __('agent-engine::ui.saved'));
    }

    public function destroy(Request $request, string $agent): RedirectResponse
    {
        $this->findAgent($request, $agent)->delete();

        return redirect()
            ->route('agent-engine.panel.agents.index')
            ->with('agent-engine.status', __('agent-engine::ui.deleted'));
    }

    protected function form(Request $request, Agent $agent): View
    {
        return view('agent-engine::agents.form', [
            'agent' => $agent,
            'apiKeys' => $this->apiKeys($request)->orderBy('provider')->get(),
            'availableSubagents' => $this->agents($request)
                ->when($agent->exists, fn ($query) => $query->whereKeyNot($agent->getKey()))
                ->orderBy('name')
                ->get(['id', 'uuid', 'name']),
        ]);
    }

    protected function save(AgentRequest $request, Agent $agent): void
    {
        $data = $request->agentData();
        $subagentIds = $request->validated('subagents', []);

        if (! empty($data['api_key_id']) && ! $this->apiKeys($request)->whereKey($data['api_key_id'])->exists()) {
            throw ValidationException::withMessages(['api_key_id' => __('agent-engine::ui.invalid_api_key')]);
        }

        $allowed = $this->agents($request)
            ->whereKey($subagentIds)
            ->when($agent->exists, fn ($query) => $query->whereKeyNot($agent->getKey()))
            ->count();

        if ($allowed !== count($subagentIds)) {
            throw ValidationException::withMessages(['subagents' => __('agent-engine::ui.invalid_subagents')]);
        }

        DB::transaction(function () use ($request, $agent, $data, $subagentIds) {
            $agent->fill($data)->save();

            $agent->tools()->delete();
            $agent->tools()->createMany($request->toolsData());

            $agent->subagents()->sync(collect($subagentIds)
                ->values()
                ->mapWithKeys(fn ($id, $position) => [$id => ['order' => $position]])
                ->all());
        });
    }
}
