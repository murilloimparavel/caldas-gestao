<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->unique()->constrained('assistant_messages')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('unit_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('queued');
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->unsignedSmallInteger('tool_calls')->default(0);
            $table->json('result')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['tenant_id', 'unit_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_runs');
    }
};
