<?php

use BuildAgents\AgentEngine\Http\Controllers\Panel\AgentController;
use BuildAgents\AgentEngine\Http\Controllers\Panel\ApiKeyController;
use BuildAgents\AgentEngine\Http\Controllers\Panel\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('agent-engine.panel.agents.index'))->name('home');

Route::resource('agents', AgentController::class)->except('show');

Route::get('agents/{agent}/playground', [PlaygroundController::class, 'show'])->name('playground');
Route::post('agents/{agent}/playground', [PlaygroundController::class, 'send'])->name('playground.send');
Route::post('agents/{agent}/playground/reset', [PlaygroundController::class, 'reset'])->name('playground.reset');

Route::resource('api-keys', ApiKeyController::class)
    ->except('show')
    ->parameters(['api-keys' => 'apiKey']);
