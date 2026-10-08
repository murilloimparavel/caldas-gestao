<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->string('source_type', 30)->nullable()->after('sale_item_id');
            $table->uuid('service_id')->nullable()->after('source_type');
            $table->uuid('package_template_id')->nullable()->after('service_id');
            $table->uuid('customer_package_id')->nullable()->after('package_template_id');
            $table->string('service_name_snapshot', 160)->nullable()->after('item_name_snapshot');
            $table->string('package_name_snapshot', 160)->nullable()->after('service_name_snapshot');

            $table->index(['tenant_id', 'unit_id', 'source_type']);
            $table->index(['tenant_id', 'unit_id', 'service_id']);
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'package_template_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('package_templates')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_package_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customer_packages')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'unit_id', 'service_id']);
            $table->dropForeign(['tenant_id', 'unit_id', 'package_template_id']);
            $table->dropForeign(['tenant_id', 'unit_id', 'customer_package_id']);
            $table->dropIndex(['tenant_id', 'unit_id', 'source_type']);
            $table->dropIndex(['tenant_id', 'unit_id', 'service_id']);
            $table->dropColumn([
                'source_type',
                'service_id',
                'package_template_id',
                'customer_package_id',
                'service_name_snapshot',
                'package_name_snapshot',
            ]);
        });
    }
};
