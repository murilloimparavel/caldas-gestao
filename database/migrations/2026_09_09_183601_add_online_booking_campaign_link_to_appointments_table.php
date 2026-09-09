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
        Schema::table('appointments', function (Blueprint $table): void {
            $table->uuid('online_booking_campaign_link_id')->nullable()->after('source');
            $table->foreign('online_booking_campaign_link_id', 'appointments_campaign_link_fk')->references('id')->on('online_booking_campaign_links')->nullOnDelete();
            $table->index(['tenant_id', 'unit_id', 'online_booking_campaign_link_id'], 'appointments_campaign_link_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign('appointments_campaign_link_fk');
            $table->dropIndex('appointments_campaign_link_idx');
            $table->dropColumn('online_booking_campaign_link_id');
        });
    }
};
