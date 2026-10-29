<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('fingerprint', 64)->unique();
            $table->string('release', 40);
            $table->string('kind', 16);
            $table->string('category', 40);
            $table->string('exception_class', 180);
            $table->string('route_name', 160)->nullable();
            $table->json('frames');
            $table->unsignedInteger('occurrences')->default(1);
            $table->string('status', 16)->default('pending')->index();
            $table->string('result', 16)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->uuid('claim_id')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_incidents');
    }
};
