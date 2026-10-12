@extends('agent-engine::layouts.panel')

@php
    $editing = $agent->exists;
    $catalog = config('agent-engine.tools', []);
    $json = fn ($value) => $value === null ? '' : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $tools = collect(old('tools', $agent->tools->map(fn ($tool) => [
        'tool_slug' => $tool->tool_slug,
        'name' => $tool->name,
        'config' => $json($tool->config),
    ])->all()))->values()->map(fn ($tool, $i) => $tool + ['key' => $i])->all();

    $subagents = collect(old('subagents', $agent->subagents->pluck('id')->all()))->map(fn ($id) => (int) $id)->values()->all();
    $available = $availableSubagents->map(fn ($sub) => ['id' => $sub->id, 'name' => $sub->name])->values()->all();
@endphp

@section('title', $editing ? $agent->name : __('agent-engine::ui.new_agent'))

@section('content')
    <div class="ae-page-header">
        <h1>{{ $editing ? __('agent-engine::ui.edit_agent') : __('agent-engine::ui.new_agent') }}</h1>
        <div class="ae-row">
            @if ($editing)
                <a class="ae-btn" href="{{ route('agent-engine.panel.playground', $agent->uuid) }}">{{ __('agent-engine::ui.test') }}</a>
            @endif
            <a class="ae-btn" href="{{ route('agent-engine.panel.agents.index') }}">{{ __('agent-engine::ui.back') }}</a>
        </div>
    </div>

    <form method="POST"
          action="{{ $editing ? route('agent-engine.panel.agents.update', $agent->uuid) : route('agent-engine.panel.agents.store') }}"
          x-data="{
              tools: @js($tools),
              nextKey: {{ count($tools) }},
              subagents: @js($subagents),
              available: @js($available),
              pick: '',
              addTool() { this.tools.push({ key: this.nextKey++, tool_slug: '', name: '', config: '' }) },
              nameOf(id) { const a = this.available.find(a => a.id === id); return a ? a.name : '#' + id },
              addSubagent() { const id = parseInt(this.pick); if (id && !this.subagents.includes(id)) this.subagents.push(id); this.pick = '' },
              move(index, delta) { const to = index + delta; if (to < 0 || to >= this.subagents.length) return; const list = [...this.subagents]; [list[index], list[to]] = [list[to], list[index]]; this.subagents = list },
          }">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="ae-card">
            <div class="ae-card-header">{{ __('agent-engine::ui.section_general') }}</div>
            <div class="ae-card-body ae-grid">
                <div class="ae-col-6">
                    <label class="ae-label" for="ae-name">{{ __('agent-engine::ui.name') }}</label>
                    <input class="ae-input" id="ae-name" name="name" value="{{ old('name', $agent->name) }}" required maxlength="255">
                    @error('name') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
                <div class="ae-col-6" style="display:flex;flex-direction:column;justify-content:flex-end;gap:6px">
                    <label class="ae-check">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $agent->is_active ?? true))>
                        {{ __('agent-engine::ui.is_active') }}
                    </label>
                    <label class="ae-check" title="{{ __('agent-engine::ui.allow_files_help') }}">
                        <input type="checkbox" name="allow_files" value="1" @checked(old('allow_files', $agent->allow_files))>
                        {{ __('agent-engine::ui.allow_files') }}
                    </label>
                </div>
                <div class="ae-col-12">
                    <label class="ae-label" for="ae-description">{{ __('agent-engine::ui.description') }}</label>
                    <input class="ae-input" id="ae-description" name="description" value="{{ old('description', $agent->description) }}">
                </div>
                <div class="ae-col-12">
                    <label class="ae-label" for="ae-instruction">{{ __('agent-engine::ui.instruction') }}</label>
                    <textarea class="ae-textarea" id="ae-instruction" name="instruction" rows="10">{{ old('instruction', $agent->instruction) }}</textarea>
                </div>
            </div>
        </div>

        <div class="ae-card">
            <div class="ae-card-header">{{ __('agent-engine::ui.section_model') }}</div>
            <div class="ae-card-body ae-grid">
                <div class="ae-col-6">
                    <label class="ae-label" for="ae-api-key">{{ __('agent-engine::ui.api_key') }}</label>
                    <select class="ae-select" id="ae-api-key" name="api_key_id">
                        <option value="">{{ __('agent-engine::ui.platform_key') }}</option>
                        @foreach ($apiKeys as $key)
                            <option value="{{ $key->id }}" @selected((string) old('api_key_id', $agent->api_key_id) === (string) $key->id)>
                                {{ $key->provider }}{{ $key->name ? ' — '.$key->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('api_key_id') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
                <div class="ae-col-6">
                    <label class="ae-label" for="ae-model">{{ __('agent-engine::ui.model') }}</label>
                    <input class="ae-input" id="ae-model" name="model_name" value="{{ old('model_name', $agent->model_name) }}"
                           placeholder="{{ config('agent-engine.default_model') }}">
                    <div class="ae-help">{{ __('agent-engine::ui.model_help') }}</div>
                    @error('model_name') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
                @foreach (['temperature' => '0.01', 'top_p' => '0.01', 'max_tokens' => '1', 'top_k' => '1'] as $field => $step)
                    <div class="ae-col-3">
                        <label class="ae-label" for="ae-{{ $field }}">{{ __('agent-engine::ui.'.$field) }}</label>
                        <input class="ae-input" id="ae-{{ $field }}" type="number" step="{{ $step }}" min="0" name="{{ $field }}"
                               value="{{ old($field, $agent->{$field}) }}">
                        @error($field) <div class="ae-error">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ae-card">
            <div class="ae-card-header">
                <span>{{ __('agent-engine::ui.tools') }}</span>
                <button type="button" class="ae-btn ae-btn-sm" @click="addTool()">{{ __('agent-engine::ui.add_tool') }}</button>
            </div>
            <div class="ae-card-body">
                <p class="ae-muted" x-show="!tools.length">{{ __('agent-engine::ui.no_tools') }}</p>
                <template x-for="(tool, index) in tools" :key="tool.key">
                    <div class="ae-repeater-item ae-grid">
                        <div class="ae-col-6">
                            <label class="ae-label">{{ __('agent-engine::ui.tool') }}</label>
                            @if ($catalog)
                                <select class="ae-select" :name="`tools[${index}][tool_slug]`" x-model="tool.tool_slug" required>
                                    <option value="">{{ __('agent-engine::ui.select') }}</option>
                                    @foreach ($catalog as $slug => $label)
                                        <option value="{{ $slug }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input class="ae-input" :name="`tools[${index}][tool_slug]`" x-model="tool.tool_slug" placeholder="slug" required>
                            @endif
                        </div>
                        <div class="ae-col-6">
                            <label class="ae-label">{{ __('agent-engine::ui.tool_name') }}</label>
                            <input class="ae-input" :name="`tools[${index}][name]`" x-model="tool.name" required>
                        </div>
                        <div class="ae-col-12">
                            <label class="ae-label">{{ __('agent-engine::ui.tool_config') }}</label>
                            <textarea class="ae-textarea ae-mono" :name="`tools[${index}][config]`" x-model="tool.config" rows="4" placeholder="{}"></textarea>
                        </div>
                        <div class="ae-col-12" style="text-align:right">
                            <button type="button" class="ae-btn ae-btn-sm ae-btn-danger" @click="tools.splice(index, 1)">{{ __('agent-engine::ui.remove') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="ae-card">
            <div class="ae-card-header">{{ __('agent-engine::ui.subagents') }}</div>
            <div class="ae-card-body">
                <p class="ae-help" style="margin-top:0">{{ __('agent-engine::ui.subagents_help') }}</p>
                <p class="ae-muted" x-show="!subagents.length">{{ __('agent-engine::ui.no_subagents') }}</p>
                <ul class="ae-list" x-show="subagents.length" style="margin-bottom:12px">
                    <template x-for="(id, index) in subagents" :key="id">
                        <li>
                            <input type="hidden" name="subagents[]" :value="id">
                            <span class="ae-grow" x-text="nameOf(id)"></span>
                            <button type="button" class="ae-icon-btn" @click="move(index, -1)" :disabled="index === 0" aria-label="{{ __('agent-engine::ui.move_up') }}">↑</button>
                            <button type="button" class="ae-icon-btn" @click="move(index, 1)" :disabled="index === subagents.length - 1" aria-label="{{ __('agent-engine::ui.move_down') }}">↓</button>
                            <button type="button" class="ae-icon-btn" @click="subagents.splice(index, 1)">{{ __('agent-engine::ui.remove') }}</button>
                        </li>
                    </template>
                </ul>
                <div class="ae-row">
                    <select class="ae-select" x-model="pick" style="max-width:320px" aria-label="{{ __('agent-engine::ui.add_subagent') }}">
                        <option value="">{{ __('agent-engine::ui.select') }}</option>
                        <template x-for="option in available.filter(a => !subagents.includes(a.id))" :key="option.id">
                            <option :value="option.id" x-text="option.name"></option>
                        </template>
                    </select>
                    <button type="button" class="ae-btn ae-btn-sm" @click="addSubagent()" :disabled="!pick">{{ __('agent-engine::ui.add_subagent') }}</button>
                </div>
            </div>
        </div>

        <div class="ae-card">
            <div class="ae-card-header">{{ __('agent-engine::ui.section_advanced') }}</div>
            <div class="ae-card-body ae-grid">
                <div class="ae-col-12">
                    <label class="ae-label" for="ae-webhook">{{ __('agent-engine::ui.webhook_url') }}</label>
                    <input class="ae-input" id="ae-webhook" type="url" name="webhook_url" value="{{ old('webhook_url', $agent->webhook_url) }}" placeholder="https://">
                    <div class="ae-help">{{ __('agent-engine::ui.webhook_url_help') }}</div>
                    @error('webhook_url') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
                <div class="ae-col-12">
                    <label class="ae-label" for="ae-schema">{{ __('agent-engine::ui.response_schema') }}</label>
                    <textarea class="ae-textarea ae-mono" id="ae-schema" name="response_schema" rows="6" placeholder='{"type": "object", "properties": {}}'>{{ old('response_schema', $json($agent->response_schema)) }}</textarea>
                    <div class="ae-help">{{ __('agent-engine::ui.response_schema_help') }}</div>
                    @error('response_schema') <div class="ae-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="ae-form-actions">
            <a class="ae-btn" href="{{ route('agent-engine.panel.agents.index') }}">{{ __('agent-engine::ui.cancel') }}</a>
            <button type="submit" class="ae-btn ae-btn-primary">{{ __('agent-engine::ui.save') }}</button>
        </div>
    </form>
@endsection
