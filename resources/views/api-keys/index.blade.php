@extends('agent-engine::layouts.panel')

@section('title', __('agent-engine::ui.nav_api_keys'))

@section('content')
    <div class="ae-page-header">
        <h1>{{ __('agent-engine::ui.nav_api_keys') }}</h1>
        <a class="ae-btn ae-btn-primary" href="{{ route('agent-engine.panel.api-keys.create') }}">{{ __('agent-engine::ui.new_api_key') }}</a>
    </div>

    <div class="ae-card">
        @if ($apiKeys->isEmpty())
            <div class="ae-empty">{{ __('agent-engine::ui.no_api_keys') }}</div>
        @else
            <div class="ae-table-wrap">
                <table class="ae-table">
                    <thead>
                        <tr>
                            <th>{{ __('agent-engine::ui.provider') }}</th>
                            <th>{{ __('agent-engine::ui.name') }}</th>
                            <th>{{ __('agent-engine::ui.api_key_value') }}</th>
                            <th class="ae-num">{{ __('agent-engine::ui.agents_count') }}</th>
                            <th class="ae-actions">{{ __('agent-engine::ui.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($apiKeys as $key)
                            <tr>
                                <td><strong>{{ $key->provider }}</strong></td>
                                <td>{{ $key->name ?? '—' }}</td>
                                <td class="ae-mono ae-muted">••••{{ substr((string) $key->api_key, -4) }}</td>
                                <td class="ae-num">{{ $key->agents_count }}</td>
                                <td class="ae-actions">
                                    <a class="ae-btn ae-btn-sm" href="{{ route('agent-engine.panel.api-keys.edit', $key->id) }}">{{ __('agent-engine::ui.edit') }}</a>
                                    <form method="POST" action="{{ route('agent-engine.panel.api-keys.destroy', $key->id) }}" style="display:inline"
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
        @endif
    </div>
@endsection
