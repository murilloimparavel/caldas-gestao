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
        Schema::table('online_booking_settings', function (Blueprint $table): void {
            $table->string('logo_image_path')->nullable()->after('cover_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_booking_settings', function (Blueprint $table): void {
            $table->dropColumn('logo_image_path');
        });
    }
};
