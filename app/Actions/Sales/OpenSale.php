<?php

namespace App\Actions\Sales;

use App\Actions\Appointments\TransferAppointmentItemsToSale;
use App\Actions\Operational\OperationalAction;
use App\Models\Appointment;
use App\Models\AppointmentSaleLink;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleStatusHistory;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OpenSale extends OperationalAction
{
    public function __construct(
        private readonly TransferAppointmentItemsToSale $appointmentItemTransfer = new TransferAppointmentItemsToSale,
    ) {
        parent::__construct();
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data, string $permission = 'sale.manage'): Sale
    {
        $unit = $this->unit($actor, $context, $permission);
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        /** @var SaleCategory|null $category */
        $category = SaleCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereKey($data['sale_category_id'] ?? null)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'sale_category_id' => 'A categoria de comanda selecionada não foi encontrada nesta unidade.',
            ]);
        }

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'sale_category_id' => 'A categoria de comanda selecionada está inativa.',
            ]);
        }

        $customerId = isset($data['customer_id']) && ! empty($data['customer_id']) ? (string) $data['customer_id'] : null;
        if ($customerId !== null) {
            $customerExists = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($customerId)
                ->exists();

            if (! $customerExists) {
                throw ValidationException::withMessages([
                    'customer_id' => 'O cliente selecionado não pertence a esta unidade.',
                ]);
            }
        }

        $appointmentId = isset($data['appointment_id']) && ! empty($data['appointment_id']) ? (string) $data['appointment_id'] : null;
        if ($appointmentId !== null) {
            $appointmentExists = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($appointmentId)
                ->exists();

            if (! $appointmentExists) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'O agendamento selecionado não pertence a esta unidade.',
                ]);
            }
        }

        $sourceId = isset($data['source_id']) && trim((string) $data['source_id']) !== ''
            ? trim((string) $data['source_id'])
            : null;

        $referenceLabel = isset($data['reference_label']) ? trim((string) $data['reference_label']) : null;
        if ($referenceLabel === '') {
            $referenceLabel = null;
        }

        $openContextKey = match ($category->uniqueness_scope) {
            'customer' => $customerId !== null ? "customer:{$customerId}" : throw ValidationException::withMessages([
                'customer_id' => 'O cliente é obrigatório para abrir comanda nesta categoria.',
            ]),
            'appointment' => $appointmentId !== null ? "appointment:{$appointmentId}" : throw ValidationException::withMessages([
                'appointment_id' => 'O agendamento é obrigatório para abrir comanda nesta categoria.',
            ]),
            'reference' => $referenceLabel !== null ? 'reference:'.Str::slug($referenceLabel) : throw ValidationException::withMessages([
                'reference_label' => 'A referência é obrigatória para abrir comanda nesta categoria.',
            ]),
            default => null,
        };

        return DB::transaction(function () use ($actor, $context, $category, $customerId, $appointmentId, $referenceLabel, $openContextKey, $sourceId, $data, $tenantId, $unitId): Sale {
            $isAutomaticAppointmentSale = is_array($data['source_metadata'] ?? null)
                && (($data['source_metadata']['created_automatically'] ?? false) === true);

            if ($sourceId !== null) {
                /** @var Sale|null $existingSourceSale */
                $existingSourceSale = Sale::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('source_id', $sourceId)
                    ->lockForUpdate()
                    ->first();

                if ($existingSourceSale !== null) {
                    if ($existingSourceSale->trashed()) {
                        $existingSourceSale->restore();
                    }

                    return $this->transferManualAppointmentItems($actor, $context, $existingSourceSale, $appointmentId, $isAutomaticAppointmentSale);
                }
            }

            if ($openContextKey !== null) {
                /** @var Sale|null $existingSale */
                $existingSale = Sale::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('sale_category_id', $category->getKey())
                    ->where('open_context_key', $openContextKey)
                    ->whereIn('status', ['draft', 'open', 'ready_to_bill'])
                    ->lockForUpdate()
                    ->first();

                if ($existingSale !== null) {
                    return $this->transferManualAppointmentItems($actor, $context, $existingSale, $appointmentId, $isAutomaticAppointmentSale);
                }
            }

            /** @var Sale $sale */
            $sale = Sale::query()->create([
                'id' => (string) Str::uuid7(),
                'source_id' => $sourceId,
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'customer_id' => $customerId,
                'sale_category_id' => $category->getKey(),
                'category_key_snapshot' => $category->key,
                'category_name_snapshot' => $category->name,
                'reference_label' => $referenceLabel,
                'open_context_key' => $openContextKey,
                'status' => 'open',
                'currency' => 'BRL',
                'total_amount_cents' => 0,
                'discount_amount_cents' => 0,
                'final_amount_cents' => 0,
                'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
                'source_metadata' => $data['source_metadata'] ?? null,
                'lock_version' => 1,
            ]);

            if ($appointmentId !== null) {
                AppointmentSaleLink::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'unit_id' => $unitId,
                    'appointment_id' => $appointmentId,
                    'sale_id' => $sale->getKey(),
                    'created_by' => $actor->getKey(),
                ]);
            }

            if ($appointmentId !== null && ! $isAutomaticAppointmentSale) {
                $this->appointmentItemTransfer->handle($actor, $context, $sale, Appointment::query()->findOrFail($appointmentId), 'calendar.manage');
            }

            SaleStatusHistory::query()->create([
                'id' => (string) Str::uuid7(),
                'sale_id' => $sale->getKey(),
                'from_status' => null,
                'to_status' => 'open',
                'user_id' => $actor->getKey(),
                'reason' => 'Abertura de comanda',
            ]);

            $this->events->record($actor, $context, 'sale.opened', $sale, [
                'key' => $category->key,
                'open_context_key' => $openContextKey,
                'appointment_id' => $appointmentId,
            ]);

            return $sale;
        }, 5);
    }

    private function transferManualAppointmentItems(User $actor, TenantContext $context, Sale $sale, ?string $appointmentId, bool $isAutomaticAppointmentSale): Sale
    {
        if ($appointmentId === null || $isAutomaticAppointmentSale) {
            return $sale;
        }

        return $this->appointmentItemTransfer->handle($actor, $context, $sale, Appointment::query()->findOrFail($appointmentId), 'calendar.manage');
    }
}
