<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantSubscription;

final class PublicBookingConfirmation
{
    /** @return array{status: 'confirmed'|'pending_confirmation', message: string} */
    public function forTenant(Tenant $tenant): array
    {
        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->latest()
            ->first();

        if ($subscription !== null && ! $subscription->grantsAccess()) {
            return [
                'status' => 'pending_confirmation',
                'message' => 'Seu pedido de agendamento foi recebido. A barbearia ainda precisa confirmar o horário; aguarde nosso retorno.',
            ];
        }

        return [
            'status' => 'confirmed',
            'message' => 'Seu horário foi confirmado.',
        ];
    }

    public function allowsCalendarSync(Tenant $tenant): bool
    {
        return $this->forTenant($tenant)['status'] === 'confirmed';
    }
}
