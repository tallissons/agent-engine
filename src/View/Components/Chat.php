<?php

namespace BuildAgents\AgentEngine\View\Components;

use BuildAgents\AgentEngine\Http\Controllers\AssetController;
use BuildAgents\AgentEngine\Models\Agent;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-agent-engine::chat :agent="$agent" title="Suporte" floating />
 */
class Chat extends Component
{
    public string $agentUuid;

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
    ) {
        $this->agentUuid = $agent instanceof Agent ? $agent->uuid : $agent;
        $this->title ??= $agent instanceof Agent ? $agent->name : __('agent-engine::ui.chat_title');
        $this->placeholder ??= __('agent-engine::ui.chat_placeholder');
    }

    public function render(): View
    {
        return view('agent-engine::components.chat', [
            'config' => [
                'endpoint' => $this->endpoint ?? route('agent-engine.widget.send'),
                'resetEndpoint' => $this->resetEndpoint ?? route('agent-engine.widget.reset'),
                // endpoints do próprio painel não precisam de token
                'token' => $this->endpoint ? null : self::encodeToken($this->agentUuid, $this->reference),
                'showTokens' => $this->showTokens,
                'floating' => $this->floating,
                'errorMessage' => __('agent-engine::errors.generic'),
            ],
            'cssUrl' => route('agent-engine.assets.css', ['v' => AssetController::version()]),
            'alpineUrl' => config('agent-engine.assets.load_alpine') ? config('agent-engine.assets.alpine_url') : null,
        ]);
    }

    public static function encodeToken(string $agentUuid, ?string $reference = null): string
    {
        return Crypt::encryptString(json_encode(['agent' => $agentUuid, 'reference' => $reference]));
    }

    /**
     * @return array{agent: string, reference: ?string}|null
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
