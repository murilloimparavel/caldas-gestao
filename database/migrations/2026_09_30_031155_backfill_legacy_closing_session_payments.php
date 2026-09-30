<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('closing_sessions')->whereNotNull('payment_method')->whereNotNull('closed_by_user_id')->orderBy('id')->chunk(200, function ($sessions): void {
            foreach ($sessions as $session) {
                if (DB::table('closing_session_payments')->where('closing_session_id', $session->id)->exists()) {
                    continue;
                }
                DB::table('closing_session_payments')->insert([
                    'id' => (string) Str::uuid7(), 'tenant_id' => $session->tenant_id, 'unit_id' => $session->unit_id,
                    'closing_session_id' => $session->id, 'cash_shift_id' => null, 'payment_method' => $session->payment_method,
                    'amount_cents' => $session->final_total_cents, 'tendered_cents' => null, 'change_cents' => 0,
                    'recorded_by_user_id' => $session->closed_by_user_id, 'recorded_at' => $session->updated_at,
                    'reversal_of_id' => null, 'is_reversal' => false, 'reversal_reason' => null,
                    'created_at' => $session->updated_at, 'updated_at' => $session->updated_at,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Historical allocation rows are intentionally retained; deleting them would erase the audit trail.
    }
};
