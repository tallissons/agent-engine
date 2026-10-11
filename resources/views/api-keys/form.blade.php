@extends('agent-engine::layouts.panel')

@php($editing = $apiKey->exists)

@section('title', $editing ? __('agent-engine::ui.edit_api_key') : __('agent-engine::ui.new_api_key'))

@section('content')
    <div class="ae-page-header">
        <h1>{{ $editing ? __('agent-engine::ui.edit_api_key') : __('agent-engine::ui.new_api_key') }}</h1>
        <a class="ae-btn" href="{{ route('agent-engine.panel.api-keys.index') }}">{{ __('agent-engine::ui.back') }}</a>
    </div>

    <form method="POST"
          action="{{ $editing ? route('agent-engine.panel.api-keys.update', $apiKey->id) : route('agent-engine.panel.api-keys.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="ae-card">
            <div class="ae-card-body ae-grid">
                <div class="ae-col-6">
                    <label class="ae-label" for="ae-provider">{{ __('agent-engine::ui.provider') }}</label>
                    <input class="ae-input" id="ae-provider" name="provider" list="ae-providers" required
                           value="{{ old('provider', $apiKey->provider) }}">
                    <datalist id="ae-providers">
                        @foreach (config('agent-engine.providers', []) as $provider)
                            <option value="{{ $provider }}">
                        @endforeach
                    </datalist>
                    @error('provider') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
                <div class="ae-col-6">
                    <label class="ae-label" for="ae-key-name">{{ __('agent-engine::ui.name') }}</label>
                    <input class="ae-input" id="ae-key-name" name="name" value="{{ old('name', $apiKey->name) }}">
                </div>
                <div class="ae-col-12">
                    <label class="ae-label" for="ae-key">{{ __('agent-engine::ui.api_key_value') }}</label>
                    <input class="ae-input ae-mono" id="ae-key" type="password" name="api_key" autocomplete="off" @required(! $editing)>
                    @if ($editing)
                        <div class="ae-help">{{ __('agent-engine::ui.api_key_keep') }}</div>
                    @endif
                    @error('api_key') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="ae-form-actions">
            <a class="ae-btn" href="{{ route('agent-engine.panel.api-keys.index') }}">{{ __('agent-engine::ui.cancel') }}</a>
            <button type="submit" class="ae-btn ae-btn-primary">{{ __('agent-engine::ui.save') }}</button>
        </div>
    </form>
@endsection
