<?php

namespace BuildAgents\AgentEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Envia o resultado do agente para o webhook_url configurado nele.
 */
class ForwardToAgentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $url,
        public array $data,
    ) {
        $this->onConnection(config('agent-engine.queue.connection'));
        $this->onQueue(config('agent-engine.queue.name'));
    }

    public function handle(): void
    {
        Http::timeout(30)->post($this->url, $this->data)->throw();
    }
}
