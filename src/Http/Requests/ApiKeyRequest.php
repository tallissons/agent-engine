<?php

namespace BuildAgents\AgentEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'provider' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            // na edição, em branco mantém a chave atual
            'api_key' => [$creating ? 'required' : 'nullable', 'string'],
        ];
    }

    public function keyData(): array
    {
        $data = $this->safe()->only(['provider', 'name', 'api_key']);

        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        return $data;
    }
}
