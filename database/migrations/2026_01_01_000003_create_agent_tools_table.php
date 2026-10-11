<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('agent-engine.tables.tools', 'agent_tools'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')
                ->constrained(config('agent-engine.tables.agents', 'agents'))
                ->cascadeOnDelete();
            $table->string('tool_slug');
            $table->string('name');
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('agent-engine.tables.tools', 'agent_tools'));
    }
};
