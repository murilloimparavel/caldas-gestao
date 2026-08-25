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
        Schema::create('membership_units', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('membership_id');
            $table->uuid('unit_id');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('created_at');

            $table->primary(['membership_id', 'unit_id']);
            $table->unique(['tenant_id', 'membership_id', 'unit_id']);
            $table->index(['tenant_id', 'unit_id', 'membership_id']);
            $table->foreign(['tenant_id', 'membership_id'])
                ->references(['tenant_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX membership_units_primary_unique ON membership_units (membership_id) WHERE is_primary IS TRUE');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX membership_units_primary_unique ON membership_units (membership_id) WHERE is_primary = 1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS membership_units_primary_unique');
        }

        Schema::dropIfExists('membership_units');
    }
};
