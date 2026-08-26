<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('online_booking_enabled')->default(false)->after('address');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('online_booking_enabled')->default(false)->after('status');
        });

        Schema::table('professionals', function (Blueprint $table): void {
            $table->boolean('online_booking_enabled')->default(false)->after('status');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'unit_id', 'phone'], 'customers_tenant_unit_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('online_booking_enabled');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('online_booking_enabled');
        });

        Schema::table('professionals', function (Blueprint $table): void {
            $table->dropColumn('online_booking_enabled');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_tenant_unit_phone_unique');
        });
    }
};
