<?php

namespace App\Actions\Appointments;

use App\Actions\Operational\OperationalAction;
use App\Actions\Sales\AddSaleItem;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TransferAppointmentItemsToSale extends OperationalAction
{
    public function __construct(
        private readonly AddSaleItem $addSaleItem = new AddSaleItem,
    ) {
        parent::__construct();
    }

    public function handle(User $actor, TenantContext $context, Sale $sale, Appointment $appointment, string $permission = 'calendar.manage'): Sale
    {
        $unit = $this->unit($actor, $context, $permission);
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId
            || $appointment->tenant_id !== $tenantId || $appointment->unit_id !== $unitId) {
            throw ValidationException::withMessages([
                'appointment_id' => 'O agendamento e a comanda precisam pertencer à unidade atual.',
            ]);
        }

        return DB::transaction(function () use ($actor, $context, $sale, $appointment, $permission, $tenantId, $unitId): Sale {
            $appointmentItems = AppointmentItem::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('appointment_id', $appointment->getKey())
                ->orderBy('position')
                ->get();

            if ($appointmentItems->isEmpty()) {
                return $sale->fresh(['items', 'appointmentLink']);
            }

            $categoryType = $sale->category()->value('type');
            if ($categoryType === 'product') {
                throw ValidationException::withMessages([
                    'sale_category_id' => 'A categoria selecionada não aceita os serviços do agendamento.',
                ]);
            }

            $lockedSale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $existingItems = $lockedSale->items()->get();

            foreach ($appointmentItems as $appointmentItem) {
                if ($this->alreadyTransferred($existingItems, $appointmentItem)) {
                    continue;
                }

                $this->addSaleItem->handle($actor, $context, $lockedSale, [
                    'item_type' => 'service',
                    'source_id' => $this->sourceId($tenantId, $unitId, $lockedSale, $appointmentItem),
                    'service_id' => $appointmentItem->service_id,
                    'professional_id' => $appointmentItem->professional_id,
                    'name_snapshot' => $appointmentItem->service_name_snapshot,
                    'unit_price_cents' => $appointmentItem->price_cents,
                    'quantity' => 1,
                    'source_metadata' => [
                        'origin' => 'appointment',
                        'appointment_id' => $appointment->getKey(),
                        'appointment_item_id' => $appointmentItem->getKey(),
                    ],
                ], $permission);
            }

            return $lockedSale->fresh(['items', 'appointmentLink']);
        }, 5);
    }

    /** @param Collection<int, SaleItem> $existingItems */
    private function alreadyTransferred(Collection $existingItems, AppointmentItem $appointmentItem): bool
    {
        return $existingItems->contains(function (SaleItem $saleItem) use ($appointmentItem): bool {
            $metadata = $saleItem->source_metadata;

            return $saleItem->source_id !== null
                && Str::endsWith($saleItem->source_id, ':'.$appointmentItem->getKey())
                || is_array($metadata) && (string) ($metadata['appointment_item_id'] ?? '') === (string) $appointmentItem->getKey();
        });
    }

    private function sourceId(string $tenantId, string $unitId, Sale $sale, AppointmentItem $appointmentItem): string
    {
        return sprintf('appointment-sale-item:%s:%s:%s:%s', $tenantId, $unitId, $sale->getKey(), $appointmentItem->getKey());
    }
}
