<?php

namespace BuildAgents\AgentEngine\Models;

use BuildAgents\AgentEngine\AgentEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $provider
 * @property string|null $name
 * @property string $api_key
 */
class AgentApiKey extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
    ];

    public function getTable(): string
    {
        // respeita setTable()
        return $this->table ?? config('agent-engine.tables.api_keys', parent::getTable());
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function agents(): HasMany
    {
        return $this->hasMany(AgentEngine::model('agent'), 'api_key_id');
    }
}
