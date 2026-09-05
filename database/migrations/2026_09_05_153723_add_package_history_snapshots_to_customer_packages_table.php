<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->string('name_snapshot')->nullable()->after('package_template_id');
            $table->integer('price_cents_snapshot')->nullable()->after('name_snapshot');
            $table->integer('total_sessions_snapshot')->nullable()->after('price_cents_snapshot');
            $table->integer('validity_days_snapshot')->nullable()->after('total_sessions_snapshot');
            $table->json('eligible_services_snapshot')->nullable()->after('validity_days_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->dropColumn([
                'name_snapshot',
                'price_cents_snapshot',
                'total_sessions_snapshot',
                'validity_days_snapshot',
                'eligible_services_snapshot',
            ]);
        });
    }
};
