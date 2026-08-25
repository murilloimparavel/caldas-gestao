<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('key', 80);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'is_system']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
