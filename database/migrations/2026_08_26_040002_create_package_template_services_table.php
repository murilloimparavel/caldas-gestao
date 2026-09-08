<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_template_services', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('package_template_id');
            $table->uuid('service_id');
            $table->timestampsTz();

            $table->primary(['package_template_id', 'service_id']);
            $table->unique(['tenant_id', 'unit_id', 'package_template_id', 'service_id']);
            $table->index(['tenant_id', 'unit_id', 'service_id']);

            $table->foreign(['tenant_id', 'unit_id', 'package_template_id'], 'pkg_tpl_services_package_fk')
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('package_templates')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'], 'pkg_tpl_services_service_fk')
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_template_services');
    }
};
