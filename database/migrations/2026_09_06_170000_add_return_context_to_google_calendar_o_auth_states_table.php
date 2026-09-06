<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_calendar_o_auth_states', function (Blueprint $table): void {
            $table->string('return_host', 253)->after('redirect_uri');
            $table->string('return_path', 255)->after('return_host');
            $table->index(['return_host', 'return_path']);
        });
    }

    public function down(): void
    {
        Schema::table('google_calendar_o_auth_states', function (Blueprint $table): void {
            $table->dropIndex('google_calendar_o_auth_states_return_host_return_path_index');
            $table->dropColumn(['return_host', 'return_path']);
        });
    }
};
