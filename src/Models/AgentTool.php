<?php

namespace BuildAgents\AgentEngine\Models;

use BuildAgents\AgentEngine\AgentEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_id
 * @property string $tool_slug
 * @property string $name
 * @property array|null $config
 */
class AgentTool extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
    ];

    public function getTable(): string
    {
        // respeita setTable()
        return $this->table ?? config('agent-engine.tables.tools', parent::getTable());
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AgentEngine::model('agent'), 'agent_id');
    }
}
