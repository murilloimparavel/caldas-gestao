<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('appointment_sales_automation_enabled')
                ->default(false)
                ->after('online_booking_enabled');
            $table->uuid('appointment_default_sale_category_id')
                ->nullable()
                ->after('appointment_sales_automation_enabled');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->foreign(
                ['tenant_id', 'appointment_default_sale_category_id'],
                'units_appointment_default_sale_category_fk',
            )->references(['tenant_id', 'id'])
                ->on('sale_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropForeign('units_appointment_default_sale_category_fk');
            $table->dropColumn([
                'appointment_sales_automation_enabled',
                'appointment_default_sale_category_id',
            ]);
        });
    }
};
