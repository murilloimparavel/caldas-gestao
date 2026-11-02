<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateOnlineBookingSettings extends OperationalAction
{
    public function __construct(
        private readonly ManageOnlineBookingHandle $handles,
        AuthorizationService $authorization,
        IdentityEventRecorder $events,
    ) {
        parent::__construct($authorization, $events);
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Unit
    {
        $unit = $this->unit($actor, $context, 'unit.update');
        $expectedVersion = (int) $data['lock_version'];
        $serviceIds = array_values(array_unique($data['service_ids']));
        $professionalIds = array_values(array_unique($data['professional_ids']));

        return DB::transaction(function () use ($actor, $context, $unit, $data, $serviceIds, $professionalIds, $expectedVersion): Unit {
            $lockedUnit = Unit::query()
                ->whereKey($unit->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUnit->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('As configurações de agendamento foram modificadas concorrentemente.');
            }

            $services = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->whereIn('id', $serviceIds)
                ->lockForUpdate()
                ->get();
            $professionals = Professional::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->whereIn('id', $professionalIds)
                ->lockForUpdate()
                ->get();

            if ($services->count() !== count($serviceIds) || $professionals->count() !== count($professionalIds)) {
                throw new AuthorizationException('Os itens selecionados não pertencem à unidade ativa.');
            }

            $this->handles->reserveForDraft(
                $context->tenant->getKey(),
                $lockedUnit->getKey(),
                $data['public_slug'],
            );

            $allActiveServices = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $allActiveServices->each(function (Service $service) use ($serviceIds): void {
                $service->forceFill([
                    'online_booking_enabled' => in_array($service->getKey(), $serviceIds, true),
                    'lock_version' => $service->lock_version + 1,
                ])->save();
            });

            $allActiveProfessionals = Professional::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $allActiveProfessionals->each(function (Professional $professional) use ($professionalIds): void {
                $professional->forceFill([
                    'online_booking_enabled' => in_array($professional->getKey(), $professionalIds, true),
                    'lock_version' => $professional->lock_version + 1,
                ])->save();
            });

            $lockedUnit->forceFill([
                'online_booking_enabled' => $data['online_booking_enabled'],
                'lock_version' => $lockedUnit->lock_version + 1,
            ])->save();

            $setting = OnlineBookingSetting::query()->firstOrNew(['unit_id' => $lockedUnit->getKey()]);
            $setting->forceFill([
                'tenant_id' => $context->tenant->getKey(), 'unit_id' => $lockedUnit->getKey(),
                'public_domain_id' => $data['public_domain_id'] ?? null,
                'public_slug' => $data['public_slug'], 'description' => $data['description'] ?? null,
                'whatsapp_phone' => $data['whatsapp_phone'] ?? null, 'phone' => $data['phone'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null, 'facebook_url' => $data['facebook_url'] ?? null,
                'website_url' => $data['website_url'] ?? null, 'brand_color' => $data['brand_color'] ?? '#2563eb',
                'booking_flow' => $data['booking_flow'] ?? 'service_first', 'minimum_notice_minutes' => $data['minimum_notice_minutes'] ?? 0,
                'public_hours' => $data['public_hours'] ?? null,
            ])->save();

            $site = OnlineBookingSite::query()->firstOrCreate(
                ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $lockedUnit->getKey()],
                ['public_domain_id' => $setting->public_domain_id, 'public_slug' => $setting->public_slug],
            );
            $site->forceFill([
                'public_domain_id' => $setting->public_domain_id,
                'public_slug' => $setting->public_slug,
                'template_key' => $data['template_key'],
            ])->save();

            $this->events->record($actor, $context, 'unit.online_booking_updated', $lockedUnit, [
                'service_ids' => $serviceIds,
                'professional_ids' => $professionalIds,
                'lock_version' => $lockedUnit->lock_version,
            ]);

            return $lockedUnit->fresh();
        }, 5);
    }
}
