<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('role_id');
            $table->uuid('permission_id');
            $table->timestampTz('created_at');

            $table->primary(['role_id', 'permission_id']);
            $table->unique(['tenant_id', 'role_id', 'permission_id']);
            $table->index(['tenant_id', 'permission_id']);
            $table->foreign(['tenant_id', 'role_id'])
                ->references(['tenant_id', 'id'])
                ->on('roles')
                ->restrictOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
