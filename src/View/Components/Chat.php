<?php

namespace BuildAgents\AgentEngine\View\Components;

use BuildAgents\AgentEngine\AgentEngine;
use BuildAgents\AgentEngine\Http\Controllers\AssetController;
use BuildAgents\AgentEngine\Models\Agent;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-agent-engine::chat :agent="$agent" title="Suporte" floating files />
 */
class Chat extends Component
{
    public string $agentUuid;

    protected ?Agent $agentModel;

    /**
     * @param  bool  $files  libera anexos (o agente também precisa de "Aceita arquivos").
     */
    public function __construct(
        Agent|string $agent,
        public ?string $title = null,
        public ?string $placeholder = null,
        public bool $floating = false,
        public string $height = '480px',
        public ?string $reference = null,
        public ?string $endpoint = null,
        public ?string $resetEndpoint = null,
        public bool $showTokens = false,
        public bool $withAssets = true,
        public bool $files = false,
    ) {
        $this->agentModel = $agent instanceof Agent
            ? $agent
            : AgentEngine::model('agent')::query()->where('uuid', $agent)->first();
        $this->agentUuid = $agent instanceof Agent ? $agent->uuid : $agent;
        $this->title ??= $this->agentModel->name ?? __('agent-engine::ui.chat_title');
        $this->placeholder ??= __('agent-engine::ui.chat_placeholder');
    }

    public function render(): View
    {
        $allowFiles = $this->files && (bool) $this->agentModel?->allow_files;
        $mimes = config('agent-engine.files.mimes', []);

        return view('agent-engine::components.chat', [
            'config' => [
                'endpoint' => $this->endpoint ?? route('agent-engine.widget.send'),
                'resetEndpoint' => $this->resetEndpoint ?? route('agent-engine.widget.reset'),
                // endpoints do próprio painel não precisam de token
                'token' => $this->endpoint ? null : self::encodeToken($this->agentUuid, $this->reference, $allowFiles),
                'showTokens' => $this->showTokens,
                'floating' => $this->floating,
                'allowFiles' => $allowFiles,
                'maxFiles' => (int) config('agent-engine.files.max_files', 5),
                'errorMessage' => __('agent-engine::errors.generic'),
            ],
            'allowFiles' => $allowFiles,
            'accept' => implode(',', array_map(fn ($ext) => '.'.$ext, $mimes)),
            'cssUrl' => route('agent-engine.assets.css', ['v' => AssetController::version()]),
            'alpineUrl' => config('agent-engine.assets.load_alpine') ? config('agent-engine.assets.alpine_url') : null,
        ]);
    }

    public static function encodeToken(string $agentUuid, ?string $reference = null, bool $files = false): string
    {
        return Crypt::encryptString(json_encode(['agent' => $agentUuid, 'reference' => $reference, 'files' => $files]));
    }

    /**
     * @return array{agent: string, reference: ?string, files?: bool}|null
     */
    public static function decodeToken(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($data) && is_string($data['agent'] ?? null) ? $data : null;
    }
}
