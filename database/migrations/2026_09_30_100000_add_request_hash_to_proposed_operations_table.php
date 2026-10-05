<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposed_operations', function (Blueprint $table): void {
            $table->char('request_hash', 64)->nullable()->after('input_hash');
        });
    }

    public function down(): void
    {
        Schema::table('proposed_operations', function (Blueprint $table): void {
            $table->dropColumn('request_hash');
        });
    }
};
