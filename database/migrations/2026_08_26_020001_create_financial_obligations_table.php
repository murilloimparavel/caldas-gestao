<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_obligations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('type', 20);
            $table->uuid('category_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('description', 255);
            $table->integer('amount_cents');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'type', 'status', 'due_date']);
            $table->index(['tenant_id', 'unit_id', 'category_id']);
            $table->index(['tenant_id', 'unit_id', 'supplier_id']);
            $table->index(['tenant_id', 'unit_id', 'customer_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'category_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('categories')
                ->nullOnDelete();
            $table->foreign(['tenant_id', 'supplier_id'])
                ->references(['tenant_id', 'id'])
                ->on('suppliers')
                ->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE financial_obligations ADD CONSTRAINT financial_obligations_type_check CHECK (type IN ('payable', 'receivable'))");
            DB::statement("ALTER TABLE financial_obligations ADD CONSTRAINT financial_obligations_status_check CHECK (status IN ('pending', 'paid', 'cancelled'))");
            DB::statement('ALTER TABLE financial_obligations ADD CONSTRAINT financial_obligations_amount_cents_check CHECK (amount_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_obligations');
    }
};
