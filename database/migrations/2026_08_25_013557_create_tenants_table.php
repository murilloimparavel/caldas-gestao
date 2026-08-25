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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 160);
            $table->string('legal_name', 200)->nullable();
            $table->string('status', 24)->default('active');
            $table->string('timezone', 64)->default('UTC');
            $table->char('default_currency', 3)->default('BRL');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('active', 'suspended', 'closed'))");
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_currency_check CHECK (default_currency ~ '^[A-Z]{3}$')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
