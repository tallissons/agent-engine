<?php

namespace BuildAgents\AgentEngine\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Define o dono dos agentes exibidos no painel.
 * Retorne null para o painel mostrar todos os agentes.
 */
interface PanelOwnerResolver
{
    public function resolve(Request $request): ?Model;
}
