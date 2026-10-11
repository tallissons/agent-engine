<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('agent-engine.tables.api_keys', 'agent_api_keys'), function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('provider');
            $table->string('name')->nullable();
            $table->text('api_key');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('agent-engine.tables.api_keys', 'agent_api_keys'));
    }
};
