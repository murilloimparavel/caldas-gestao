<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_session_sales', function (Blueprint $table): void {
            $table->uuid('closing_session_id');
            $table->uuid('sale_id');

            $table->primary(['closing_session_id', 'sale_id']);
            $table->foreign('closing_session_id')->references('id')->on('closing_sessions')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_session_sales');
    }
};
