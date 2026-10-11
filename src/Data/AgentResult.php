<?php

namespace BuildAgents\AgentEngine\Data;

use Illuminate\Contracts\Support\Arrayable;

class AgentResult implements Arrayable
{
    public const COMPLETED = 'completed';

    public const PROCESSING = 'processing';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly string $agentUuid,
        public readonly ?string $sessionId = null,
        public readonly mixed $content = null,
        public readonly ?array $tokenUsage = null,
        public readonly ?string $externalReference = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly array $raw = [],
    ) {
    }

    public function successful(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::PROCESSING;
    }

    public function failed(): bool
    {
        return $this->status === self::FAILED;
    }

    public function promptTokens(): int
    {
        return (int) ($this->tokenUsage['prompt_token_count'] ?? 0);
    }

    public function candidatesTokens(): int
    {
        return (int) ($this->tokenUsage['candidates_token_count'] ?? 0);
    }

    public function totalTokens(): int
    {
        return (int) ($this->tokenUsage['total_token_count'] ?? 0);
    }

    /**
     * Mesmo formato que o AgentService original devolvia.
     */
    public function toArray(bool $withTokenUsage = false): array
    {
        return match ($this->status) {
            self::COMPLETED => array_filter([
                'session_id' => $this->sessionId,
                'external_reference' => $this->externalReference ?: null,
                'content' => $this->content,
                'token_usage' => $withTokenUsage ? $this->tokenUsage : null,
            ], fn ($value) => $value !== null),
            self::PROCESSING => [
                'agent_id' => $this->agentUuid,
                'external_reference' => $this->externalReference,
                'session_id' => $this->sessionId,
                'status' => $this->status,
            ],
            default => ['error' => $this->error],
        };
    }
}
