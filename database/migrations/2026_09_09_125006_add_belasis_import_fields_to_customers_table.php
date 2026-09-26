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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->string('phone_normalized', 40)->nullable()->after('phone');
            $table->json('source_metadata')->nullable()->after('notes');

            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'customers_tenant_unit_source_unique');
            $table->index(['tenant_id', 'unit_id', 'phone_normalized'], 'customers_tenant_unit_phone_normalized_index');
        });

        DB::table('customers')->select(['id', 'phone'])->orderBy('id')->chunkById(500, function ($customers): void {
            foreach ($customers as $customer) {
                $phone = preg_replace('/\D+/', '', (string) $customer->phone) ?: null;
                DB::table('customers')->where('id', $customer->id)->update(['phone_normalized' => $phone]);
            }
        }, 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_tenant_unit_source_unique');
            $table->dropIndex('customers_tenant_unit_phone_normalized_index');
            $table->dropColumn(['source_id', 'phone_normalized', 'source_metadata']);
        });
    }
};
