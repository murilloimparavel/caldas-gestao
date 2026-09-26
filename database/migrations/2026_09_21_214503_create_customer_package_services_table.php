<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_package_services', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_package_id');
            $table->uuid('service_id');
            $table->unsignedInteger('allocated_quantity');
            $table->unsignedInteger('remaining_quantity');
            $table->timestampsTz();
            $table->primary(['customer_package_id', 'service_id']);
            $table->index(['tenant_id', 'unit_id', 'service_id']);
            $table->foreign(['tenant_id', 'unit_id', 'customer_package_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customer_packages')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->restrictOnDelete();
        });

        DB::table('customer_packages')
            ->join('package_template_services', 'package_template_services.package_template_id', '=', 'customer_packages.package_template_id')
            ->select([
                'customer_packages.tenant_id',
                'customer_packages.unit_id',
                'customer_packages.id as customer_package_id',
                'package_template_services.service_id',
                'package_template_services.included_quantity',
            ])
            ->orderBy('customer_packages.id')
            ->chunk(500, function ($rows): void {
                $now = now();
                DB::table('customer_package_services')->insert($rows->map(static fn ($row): array => [
                    'tenant_id' => $row->tenant_id,
                    'unit_id' => $row->unit_id,
                    'customer_package_id' => $row->customer_package_id,
                    'service_id' => $row->service_id,
                    'allocated_quantity' => $row->included_quantity,
                    'remaining_quantity' => $row->included_quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_package_services');
    }
};
