@extends('agent-engine::layouts.panel')

@section('title', __('agent-engine::ui.playground').' · '.$agent->name)

@section('content')
    <div class="ae-page-header">
        <div>
            <h1>{{ $agent->name }}</h1>
            <div class="ae-help">{{ __('agent-engine::ui.playground_help') }}</div>
        </div>
        <div class="ae-row">
            <a class="ae-btn" href="{{ route('agent-engine.panel.agents.edit', $agent->uuid) }}">{{ __('agent-engine::ui.edit') }}</a>
            <a class="ae-btn" href="{{ route('agent-engine.panel.agents.index') }}">{{ __('agent-engine::ui.back') }}</a>
        </div>
    </div>

    <x-agent-engine::chat
        :agent="$agent"
        :title="__('agent-engine::ui.playground')"
        height="min(640px, calc(100vh - 200px))"
        :endpoint="route('agent-engine.panel.playground.send', $agent->uuid)"
        :reset-endpoint="route('agent-engine.panel.playground.reset', $agent->uuid)"
        :show-tokens="true"
        :files="true"
        :with-assets="false"
    />
@endsection
