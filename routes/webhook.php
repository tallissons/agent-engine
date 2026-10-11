<?php

use BuildAgents\AgentEngine\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhook/{secret}', WebhookController::class)
    ->name(config('agent-engine.webhook.route_name', 'agent-engine.webhook'));
