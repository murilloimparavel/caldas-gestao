<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->uuid('user_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();

            $table->index(['sale_id', 'created_at']);
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_status_histories');
    }
};
