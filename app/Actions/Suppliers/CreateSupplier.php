<?php

namespace App\Actions\Suppliers;

use App\Actions\Operational\OperationalAction;
use App\Models\Supplier;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateSupplier extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Supplier
    {
        $unit = $this->unit($actor, $context, 'supplier.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Supplier {
            $supplier = Supplier::query()->create([
                ...$data,
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'supplier.created', $supplier, [
                'is_active' => $supplier->is_active,
            ]);

            return $supplier;
        }, 5);
    }
}
