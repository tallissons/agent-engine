<?php

namespace BuildAgents\AgentEngine\Models;

use BuildAgents\AgentEngine\AgentEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string|null $instruction
 * @property int|null $api_key_id
 * @property string|null $model_name
 * @property float|null $temperature
 * @property int|null $max_tokens
 * @property float|null $top_p
 * @property int|null $top_k
 * @property array|null $response_schema
 * @property string|null $webhook_url
 * @property bool $is_active
 * @property bool $allow_files
 * @property-read AgentApiKey|null $apiKey
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AgentTool> $tools
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Agent> $subagents
 * @property-read Model|null $owner
 */
class Agent extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = [
        'is_active' => true,
        'allow_files' => false,
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'top_p' => 'float',
        'top_k' => 'integer',
        'response_schema' => 'array',
        'is_active' => 'boolean',
        'allow_files' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (empty($agent->uuid)) {
                $agent->uuid = (string) Str::uuid();
            }
        });
    }

    public function getTable(): string
    {
        // respeita setTable() (aliases de consultas auto-relacionadas, ex.: withCount('subagents'))
        return $this->table ?? config('agent-engine.tables.agents', parent::getTable());
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(AgentEngine::model('api_key'), 'api_key_id');
    }

    public function tools(): HasMany
    {
        return $this->hasMany(AgentEngine::model('tool'), 'agent_id');
    }

    public function subagents(): BelongsToMany
    {
        return $this->belongsToMany(
            static::class,
            config('agent-engine.tables.subagents', 'agent_subagents'),
            'agent_id',
            'subagent_id'
        )
            ->using(AgentSubagent::class)
            ->withPivot('order')
            ->withTimestamps()
            ->orderByPivot('order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Indica se o agente usa uma api key própria em vez da chave da plataforma.
     */
    public function usesOwnApiKey(): bool
    {
        return $this->apiKey !== null;
    }
}
