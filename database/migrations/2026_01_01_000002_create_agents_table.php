<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('agent-engine.tables.agents', 'agents'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('instruction')->nullable();
            $table->foreignId('api_key_id')
                ->nullable()
                ->constrained(config('agent-engine.tables.api_keys', 'agent_api_keys'))
                ->nullOnDelete();
            $table->string('model_name')->nullable();
            $table->decimal('temperature', 4, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->decimal('top_p', 4, 2)->nullable();
            $table->unsignedInteger('top_k')->nullable();
            $table->json('response_schema')->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('agent-engine.tables.agents', 'agents'));
    }
};
