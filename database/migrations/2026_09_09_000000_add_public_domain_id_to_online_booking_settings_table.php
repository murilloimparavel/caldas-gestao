<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_booking_settings', function (Blueprint $table): void {
            $table->uuid('public_domain_id')->nullable()->after('unit_id');
            $table->foreign('public_domain_id')->references('id')->on('tenant_domains')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('online_booking_settings', function (Blueprint $table): void {
            $table->dropForeign(['public_domain_id']);
            $table->dropColumn('public_domain_id');
        });
    }
};
