<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_cents')->default(0)->after('subscription_plan_id');
            $table->string('billing_cycle', 20)->default('monthly')->after('price_cents');
            $table->index(['tenant_id', 'unit_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('customer_subscriptions_tenant_id_unit_id_customer_id_status_index');
            $table->dropColumn(['price_cents', 'billing_cycle']);
        });
    }
};
