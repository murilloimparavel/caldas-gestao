<?php

namespace App\Actions\PublicBooking;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\OnlineBookingSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\CalendarAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreatePublicAppointment
{
    public function __construct(private readonly CalendarAvailability $availability) {}

    /** @param array{service_id: string, professional_id: string, starts_at: string, name: string, phone: string, online_booking_campaign_link_id?: string|null} $data */
    public function handle(Tenant $tenant, Unit $unit, array $data): Appointment
    {
        $service = Service::query()
            ->whereKey($data['service_id'])
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($unit)
            ->where('status', 'active')
            ->where('online_booking_enabled', true)
            ->firstOrFail();
        $professional = Professional::query()
            ->whereKey($data['professional_id'])
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($unit)
            ->where('status', 'active')
            ->where('online_booking_enabled', true)
            ->firstOrFail();

        if (! $professional->services()->whereKey($service->getKey())->exists()) {
            abort(404);
        }

        $timezone = (string) ($unit->timezone ?? $tenant->timezone ?? config('app.timezone'));
        $startsAt = CarbonImmutable::parse($data['starts_at'], $timezone)->setTimezone($timezone);
        $now = CarbonImmutable::now($timezone);

        $setting = $unit->onlineBookingSetting;
        $minimumNoticeMinutes = $setting instanceof OnlineBookingSetting ? $setting->minimum_notice_minutes : 0;
        if ($startsAt->isBefore($now->addMinutes((int) $minimumNoticeMinutes)) || $startsAt->isAfter($now->addDays(31))) {
            throw ValidationException::withMessages(['starts_at' => 'The selected time is no longer available.']);
        }
        $hours = $setting instanceof OnlineBookingSetting ? $setting->getAttribute('public_hours') : null;
        if (is_array($hours)) {
            $window = $hours[(string) $startsAt->dayOfWeek] ?? $hours[$startsAt->dayOfWeek] ?? null;
            if (! is_array($window) || ($window['enabled'] ?? true) === false || $startsAt->format('H:i') < ($window['starts_at'] ?? '') || $startsAt->addMinutes((int) $service->duration_minutes)->format('H:i') > ($window['ends_at'] ?? '')) {
                throw ValidationException::withMessages(['starts_at' => 'The selected time is outside public booking hours.']);
            }
        }
        $duration = (int) $service->duration_minutes;
        $endsAt = $startsAt->addMinutes($duration);
        $phone = $this->normalizePhone($data['phone']);

        $lockKey = sprintf('public-booking:%s:%s:%s', $tenant->getKey(), $unit->getKey(), $professional->getKey());

        return Cache::lock($lockKey, 10)->block(5, function () use ($tenant, $unit, $data, $service, $professional, $startsAt, $endsAt, $timezone, $duration, $phone): Appointment {
            return DB::transaction(function () use ($tenant, $unit, $data, $service, $professional, $startsAt, $endsAt, $timezone, $duration, $phone): Appointment {
                $this->availability->assertAvailable(
                    (string) $tenant->getKey(),
                    (string) $unit->getKey(),
                    (string) $professional->getKey(),
                    $startsAt,
                    $endsAt,
                );

                $customer = Customer::query()
                    ->whereBelongsTo($tenant)
                    ->whereBelongsTo($unit)
                    ->where('phone', $phone)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($customer === null) {
                    try {
                        $customer = DB::transaction(fn (): Customer => Customer::query()->create([
                            'id' => (string) Str::uuid7(),
                            'tenant_id' => $tenant->getKey(),
                            'unit_id' => $unit->getKey(),
                            'name' => $data['name'],
                            'phone' => $phone,
                            'status' => 'active',
                        ]), 1);
                    } catch (QueryException $exception) {
                        $customer = Customer::query()
                            ->whereBelongsTo($tenant)
                            ->whereBelongsTo($unit)
                            ->where('phone', $phone)
                            ->where('status', 'active')
                            ->lockForUpdate()
                            ->first();

                        if ($customer === null) {
                            throw $exception;
                        }
                    }
                } else {
                    $customer->update(['name' => $data['name']]);
                }

                $appointment = Appointment::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->getKey(),
                    'unit_id' => $unit->getKey(),
                    'customer_id' => $customer->getKey(),
                    'professional_id' => $professional->getKey(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'timezone' => $timezone,
                    'status' => 'scheduled',
                    'source' => 'online',
                    'online_booking_campaign_link_id' => $data['online_booking_campaign_link_id'] ?? null,
                    'reminder_enabled' => true,
                    'fit_in' => false,
                    'lock_version' => 0,
                ]);

                AppointmentItem::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->getKey(),
                    'unit_id' => $unit->getKey(),
                    'appointment_id' => $appointment->getKey(),
                    'service_id' => $service->getKey(),
                    'professional_id' => $professional->getKey(),
                    'service_name_snapshot' => $service->name,
                    'duration_minutes' => $duration,
                    'price_cents' => $service->price_cents,
                    'currency' => $tenant->default_currency,
                    'position' => 1,
                ]);

                return $appointment->fresh('items');
            }, 5);
        });
    }

    private function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }
}
