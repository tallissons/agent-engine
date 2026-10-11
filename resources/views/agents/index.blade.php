@extends('agent-engine::layouts.panel')

@section('title', __('agent-engine::ui.nav_agents'))

@section('content')
    <div class="ae-page-header">
        <h1>{{ __('agent-engine::ui.nav_agents') }}</h1>
        <div class="ae-row">
            <form method="GET" action="{{ route('agent-engine.panel.agents.index') }}">
                <input class="ae-input" type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('agent-engine::ui.search') }}" aria-label="{{ __('agent-engine::ui.search') }}">
            </form>
            <a class="ae-btn ae-btn-primary" href="{{ route('agent-engine.panel.agents.create') }}">
                {{ __('agent-engine::ui.new_agent') }}
            </a>
        </div>
    </div>

    <div class="ae-card">
        @if ($agents->isEmpty())
            <div class="ae-empty">{{ __('agent-engine::ui.no_agents') }}</div>
        @else
            <div class="ae-table-wrap">
                <table class="ae-table">
                    <thead>
                        <tr>
                            <th>{{ __('agent-engine::ui.name') }}</th>
                            <th>{{ __('agent-engine::ui.status') }}</th>
                            <th class="ae-num">{{ __('agent-engine::ui.tools_count') }}</th>
                            <th class="ae-num">{{ __('agent-engine::ui.subagents_count') }}</th>
                            <th class="ae-actions">{{ __('agent-engine::ui.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                            <tr>
                                <td>
                                    <a href="{{ route('agent-engine.panel.agents.edit', $agent->uuid) }}"><strong>{{ $agent->name }}</strong></a>
                                    @if ($agent->description)
                                        <div class="ae-muted">{{ \Illuminate\Support\Str::limit($agent->description, 90) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($agent->is_active)
                                        <span class="ae-badge ae-badge-success">{{ __('agent-engine::ui.active') }}</span>
                                    @else
                                        <span class="ae-badge">{{ __('agent-engine::ui.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="ae-num">{{ $agent->tools_count }}</td>
                                <td class="ae-num">{{ $agent->subagents_count }}</td>
                                <td class="ae-actions">
                                    <a class="ae-btn ae-btn-sm" href="{{ route('agent-engine.panel.playground', $agent->uuid) }}">{{ __('agent-engine::ui.test') }}</a>
                                    <a class="ae-btn ae-btn-sm" href="{{ route('agent-engine.panel.agents.edit', $agent->uuid) }}">{{ __('agent-engine::ui.edit') }}</a>
                                    <form method="POST" action="{{ route('agent-engine.panel.agents.destroy', $agent->uuid) }}" style="display:inline"
                                          onsubmit="return confirm(@js(__('agent-engine::ui.confirm_delete')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ae-btn ae-btn-sm ae-btn-danger">{{ __('agent-engine::ui.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($agents->hasPages())
                <div class="ae-pagination">
                    <span>
                        @if ($agents->previousPageUrl())
                            <a class="ae-btn ae-btn-sm" href="{{ $agents->previousPageUrl() }}">{{ __('agent-engine::ui.previous') }}</a>
                        @endif
                    </span>
                    <span>
                        @if ($agents->nextPageUrl())
                            <a class="ae-btn ae-btn-sm" href="{{ $agents->nextPageUrl() }}">{{ __('agent-engine::ui.next') }}</a>
                        @endif
                    </span>
                </div>
            @endif
        @endif
    </div>
@endsection
