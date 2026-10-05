<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TenantDomain;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single read-only predicate shared by setup diagnostics and publication.
 */
final class OnlineBookingReadiness
{
    /**
     * @param  array<string, mixed>|null  $content
     * @return array{publishable: bool, blockers: list<string>, has_selected_active_service: bool, has_selected_active_professional: bool, has_selected_service_professional_pair: bool, domain_ready: bool, unit_enabled: bool, has_active_service: bool, has_active_professional: bool, has_service_professional_pair: bool, has_whatsapp: bool}
     */
    public function check(TenantContext $context, ?OnlineBookingSite $site, ?array $content): array
    {
        $unit = $context->unit;
        $tenantId = (string) $context->tenant->getKey();
        $unitId = $unit instanceof Unit ? (string) $unit->getKey() : '';
        $serviceIds = $this->selectedIds($content, 'service_ids');
        $professionalIds = $this->selectedIds($content, 'professional_ids');
        $hasWhatsapp = filled(OnlineBookingSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->value('whatsapp_phone')) || Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereIn('id', $professionalIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->exists();

        $hasSelectedActiveService = $unit instanceof Unit
            && $serviceIds !== []
            && Service::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'active')
                ->whereIn('id', $serviceIds)
                ->exists();
        $hasSelectedActiveProfessional = $unit instanceof Unit
            && $professionalIds !== []
            && Professional::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'active')
                ->whereIn('id', $professionalIds)
                ->exists();
        $hasSelectedServiceProfessionalPair = $hasSelectedActiveService
            && $hasSelectedActiveProfessional
            && Professional::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'active')
                ->whereIn('id', $professionalIds)
                ->whereHas('services', fn (Builder $query): Builder => $query
                    ->where('services.tenant_id', $tenantId)
                    ->where('services.unit_id', $unitId)
                    ->where('services.status', 'active')
                    ->whereIn('services.id', $serviceIds))
                ->exists();
        $domainReady = $site instanceof OnlineBookingSite
            && ($site->public_domain_id === null || TenantDomain::query()
                ->whereKey($site->public_domain_id)
                ->where('tenant_id', $tenantId)
                ->where('kind', 'public')
                ->where('status', 'active')
                ->exists());

        $blockers = [];

        if (! $unit instanceof Unit || ! $unit->online_booking_enabled) {
            $blockers[] = 'unit_online_booking_disabled';
        }

        if (! $site instanceof OnlineBookingSite || ! $site->draft instanceof OnlineBookingDraft) {
            $blockers[] = 'booking_draft_missing';
        }

        if (! $hasSelectedActiveService) {
            $blockers[] = 'active_service_missing';
        }

        if (! $hasSelectedActiveProfessional) {
            $blockers[] = 'active_professional_missing';
        }

        if (! $hasSelectedServiceProfessionalPair) {
            $blockers[] = 'service_professional_pair_missing';
        }

        if ($site instanceof OnlineBookingSite && ! $domainReady) {
            $blockers[] = 'public_domain_unavailable';
        }

        return [
            'publishable' => $blockers === [],
            'blockers' => $blockers,
            'has_selected_active_service' => $hasSelectedActiveService,
            'has_selected_active_professional' => $hasSelectedActiveProfessional,
            'has_selected_service_professional_pair' => $hasSelectedServiceProfessionalPair,
            'domain_ready' => $domainReady,
            'unit_enabled' => $unit instanceof Unit && $unit->online_booking_enabled,
            'has_active_service' => $hasSelectedActiveService,
            'has_active_professional' => $hasSelectedActiveProfessional,
            'has_service_professional_pair' => $hasSelectedServiceProfessionalPair,
            'has_whatsapp' => $hasWhatsapp,
        ];
    }

    /** @param array<string, mixed>|null $content
     * @return list<string>
     */
    private function selectedIds(?array $content, string $key): array
    {
        $values = $content[$key] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }
}
