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
            $handles = [];

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
                ->each(function (object $site) use (&$knownUnits, &$handles): void {
                    $this->queueHandle($handles, $site->tenant_id, $site->unit_id, $site->public_slug);
                    $knownUnits[$site->tenant_id.':'.$site->unit_id] = true;
                });

            DB::table('online_booking_settings')
                ->select(['tenant_id', 'unit_id', 'public_slug'])
                ->orderBy('id')
                ->each(function (object $setting) use (&$knownUnits, &$handles): void {
                    $unitKey = $setting->tenant_id.':'.$setting->unit_id;

                    if (! isset($knownUnits[$unitKey])) {
                        $this->queueHandle($handles, $setting->tenant_id, $setting->unit_id, $setting->public_slug);
                        $knownUnits[$unitKey] = true;
                    }
                });

            $this->assertNoExistingCollisions($handles);

            foreach ($handles as $handle) {
                $this->insertHandle($handle['tenant_id'], $handle['unit_id'], $handle['handle']);
            }
        });
    }

    public function down(): void
    {
        // This data backfill is intentionally irreversible. A rollback must not delete handles
        // created or renamed after deployment; use a forward migration for any data repair.
    }

    /** @param array<string, array{tenant_id: string, unit_id: string, handle: string}> $handles */
    private function queueHandle(array &$handles, string $tenantId, string $unitId, string $handle): void
    {
        $normalizedHandle = Str::slug(trim($handle));

        if ($normalizedHandle === '') {
            throw new RuntimeException('Online booking handles must contain at least one slug character.');
        }

        $existing = $handles[$normalizedHandle] ?? null;

        if ($existing !== null) {
            if ($existing['tenant_id'] === $tenantId && $existing['unit_id'] === $unitId) {
                return;
            }

            throw new RuntimeException("Online booking handle '{$normalizedHandle}' is claimed by multiple units.");
        }

        $handles[$normalizedHandle] = [
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'handle' => $normalizedHandle,
        ];
    }

    /** @param array<string, array{tenant_id: string, unit_id: string, handle: string}> $handles */
    private function assertNoExistingCollisions(array $handles): void
    {
        if ($handles === []) {
            return;
        }

        $existingHandles = DB::table('online_booking_handles')
            ->get(['tenant_id', 'unit_id', 'handle']);

        foreach ($existingHandles as $existing) {
            $normalizedHandle = Str::slug(trim($existing->handle));
            $candidate = $handles[$normalizedHandle] ?? null;

            if ($candidate === null) {
                continue;
            }

            if ($existing->handle !== $normalizedHandle) {
                throw new RuntimeException("Online booking handle '{$existing->handle}' must be canonical before the backfill can proceed.");
            }

            if ($existing->tenant_id !== $candidate['tenant_id'] || $existing->unit_id !== $candidate['unit_id']) {
                throw new RuntimeException("Online booking handle '{$normalizedHandle}' is claimed by multiple units.");
            }
        }
    }

    private function insertHandle(string $tenantId, string $unitId, string $handle): void
    {
        if (DB::table('online_booking_handles')->where('handle', $handle)->exists()) {
            return;
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
