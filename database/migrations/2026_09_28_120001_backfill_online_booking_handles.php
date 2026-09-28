<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $knownUnits = [];

            DB::table('online_booking_sites')
                ->leftJoin('online_booking_publications', 'online_booking_publications.id', '=', 'online_booking_sites.active_publication_id')
                ->leftJoin('online_booking_settings', function ($join): void {
                    $join->on('online_booking_settings.tenant_id', '=', 'online_booking_sites.tenant_id')
                        ->on('online_booking_settings.unit_id', '=', 'online_booking_sites.unit_id');
                })
                ->select([
                    'online_booking_sites.tenant_id',
                    'online_booking_sites.unit_id',
                    DB::raw('COALESCE(online_booking_publications.public_slug, online_booking_settings.public_slug, online_booking_sites.public_slug) as public_slug'),
                ])
                ->orderBy('online_booking_sites.id')
                ->each(function (object $site) use (&$knownUnits): void {
                    $this->insertHandle($site->tenant_id, $site->unit_id, $site->public_slug);
                    $knownUnits[$site->tenant_id.':'.$site->unit_id] = true;
                });

            DB::table('online_booking_settings')
                ->select(['tenant_id', 'unit_id', 'public_slug'])
                ->orderBy('id')
                ->each(function (object $setting) use (&$knownUnits): void {
                    $unitKey = $setting->tenant_id.':'.$setting->unit_id;

                    if (! isset($knownUnits[$unitKey])) {
                        $this->insertHandle($setting->tenant_id, $setting->unit_id, $setting->public_slug);
                        $knownUnits[$unitKey] = true;
                    }
                });
        });
    }

    public function down(): void
    {
        DB::table('online_booking_handles')->delete();
    }

    private function insertHandle(string $tenantId, string $unitId, string $handle): void
    {
        $existing = DB::table('online_booking_handles')->where('handle', $handle)->first();

        if ($existing !== null) {
            if ($existing->tenant_id === $tenantId && $existing->unit_id === $unitId) {
                return;
            }

            throw new RuntimeException("Online booking handle '{$handle}' is claimed by multiple units.");
        }

        DB::table('online_booking_handles')->insert([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'handle' => $handle,
            'status' => 'current',
            'redirect_until' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
