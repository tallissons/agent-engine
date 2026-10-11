<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $agents = config('agent-engine.tables.agents', 'agents');

        Schema::create(config('agent-engine.tables.subagents', 'agent_subagents'), function (Blueprint $table) use ($agents) {
            $table->id();
            $table->foreignId('agent_id')->constrained($agents)->cascadeOnDelete();
            $table->foreignId('subagent_id')->constrained($agents)->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['agent_id', 'subagent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('agent-engine.tables.subagents', 'agent_subagents'));
    }
};
