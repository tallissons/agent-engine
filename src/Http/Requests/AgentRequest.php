<?php

namespace BuildAgents\AgentEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'allow_files' => $this->boolean('allow_files'),
            'tools' => array_values($this->input('tools', [])),
            'subagents' => array_values($this->input('subagents', [])),
        ]);
    }

    public function rules(): array
    {
        $catalog = array_keys(config('agent-engine.tools', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instruction' => ['nullable', 'string'],
            'api_key_id' => ['nullable', 'integer'],
            'model_name' => ['nullable', 'required_with:api_key_id', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'max_tokens' => ['nullable', 'integer', 'min:1'],
            'top_p' => ['nullable', 'numeric', 'between:0,1'],
            'top_k' => ['nullable', 'integer', 'min:1'],
            'response_schema' => ['nullable', 'json'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
            'allow_files' => ['boolean'],
            'tools' => ['array'],
            'tools.*.tool_slug' => array_filter(['required', 'string', 'max:255', $catalog ? Rule::in($catalog) : null]),
            'tools.*.name' => ['required', 'string', 'max:255'],
            'tools.*.config' => ['nullable', 'json'],
            'subagents' => ['array'],
            'subagents.*' => ['integer', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tools.*.tool_slug' => 'tool',
            'tools.*.name' => 'nome da tool',
            'tools.*.config' => 'config da tool',
        ];
    }

    /**
     * Campos da tabela agents, já convertidos.
     */
    public function agentData(): array
    {
        $data = $this->safe()->except(['tools', 'subagents']);
        $data['response_schema'] = filled($data['response_schema'] ?? null)
            ? json_decode($data['response_schema'], true)
            : null;

        return $data;
    }

    /**
     * @return array<int, array{tool_slug: string, name: string, config: array|null}>
     */
    public function toolsData(): array
    {
        return collect($this->validated('tools', []))
            ->map(fn (array $tool) => [
                'tool_slug' => $tool['tool_slug'],
                'name' => $tool['name'],
                'config' => filled($tool['config'] ?? null) ? json_decode($tool['config'], true) : null,
            ])
            ->all();
    }
}
