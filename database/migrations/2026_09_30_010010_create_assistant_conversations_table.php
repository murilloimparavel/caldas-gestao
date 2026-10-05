<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->cascadeOnDelete();
            $table->index(['user_id', 'tenant_id', 'unit_id', 'updated_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversations');
    }
};
