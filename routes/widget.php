<?php

use BuildAgents\AgentEngine\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::post('send', [WidgetController::class, 'send'])->name('send');
Route::post('reset', [WidgetController::class, 'reset'])->name('reset');
