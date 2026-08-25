<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->uuid('category_id')->nullable()->after('unit_id');
            $table->index(['tenant_id', 'unit_id', 'category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['tenant_id', 'unit_id', 'category_id']);
            $table->dropColumn('category_id');
        });
    }
};
