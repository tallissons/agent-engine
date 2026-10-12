<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('agent-engine.tables.agents', 'agents'), function (Blueprint $table) {
            $table->boolean('allow_files')->default(false)->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table(config('agent-engine.tables.agents', 'agents'), function (Blueprint $table) {
            $table->dropColumn('allow_files');
        });
    }
};
