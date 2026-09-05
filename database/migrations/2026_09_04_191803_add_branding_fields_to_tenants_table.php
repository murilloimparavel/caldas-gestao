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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('brand_name', 160)->nullable()->after('name');
            $table->string('logo_url', 2048)->nullable()->after('brand_name');
            $table->string('favicon_url', 2048)->nullable()->after('logo_url');
            $table->string('primary_color', 32)->nullable()->after('favicon_url');
            $table->string('accent_color', 32)->nullable()->after('primary_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['brand_name', 'logo_url', 'favicon_url', 'primary_color', 'accent_color']);
        });
    }
};
