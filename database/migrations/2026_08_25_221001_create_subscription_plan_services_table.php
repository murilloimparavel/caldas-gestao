<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_services', function (Blueprint $table): void {
            $table->uuid('subscription_plan_id');
            $table->uuid('service_id');
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->integer('max_uses_per_cycle')->nullable();
            $table->timestampsTz();

            $table->primary(['subscription_plan_id', 'service_id']);

            $table->foreign(['tenant_id', 'unit_id', 'subscription_plan_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('subscription_plans')
                ->cascadeOnDelete();

            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_services');
    }
};
