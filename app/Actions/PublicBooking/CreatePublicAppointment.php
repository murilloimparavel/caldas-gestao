<?php

namespace App\Actions\PublicBooking;

use App\Actions\Appointments\CreateAppointmentSale;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\AuditEventWriter;
use App\Support\CalendarAvailability;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\PublicBookingConfirmation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreatePublicAppointment
{
    public function __construct(
        private readonly CalendarAvailability $availability,
        private readonly CreateAppointmentSale $createAppointmentSale = new CreateAppointmentSale,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly PublicBookingConfirmation $confirmation = new PublicBookingConfirmation,
    ) {}

    /** @param array{service_id?: string|null, service_ids?: list<string>, professional_id: string, starts_at: string, name: string, phone: string, email?: string|null, notes?: string|null, online_booking_campaign_link_id?: string|null} $data */
    public function handle(Tenant $tenant, Unit $unit, array $data): Appointment
    {
        $serviceIds = array_values(array_unique(array_filter(
            $data['service_ids'] ?? [$data['service_id'] ?? null],
            'is_string',
        )));
        $publication = $this->activePublication($tenant, $unit);
        $publicationContent = is_array($publication?->content) ? $publication->content : null;
        $publishedServiceIds = is_array($publicationContent)
            ? array_values(array_filter($publicationContent['service_ids'] ?? [], 'is_string'))
            : null;
        $services = Service::query()
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($unit)
            ->where('status', 'active')
            ->when($publishedServiceIds === null, fn ($query) => $query->where('online_booking_enabled', true))
            ->whereIn('id', $serviceIds)
            ->when($publishedServiceIds !== null, fn ($query) => $query->whereIn('id', $publishedServiceIds))
            ->with('category')
            ->get();
        if ($services->count() !== count($serviceIds)) {
            abort(404);
        }
        $service = $services->firstOrFail();
        $professional = Professional::query()
            ->whereKey($data['professional_id'])
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($unit)
            ->where('status', 'active')
            ->when($publishedServiceIds === null, fn ($query) => $query->where('online_booking_enabled', true))
            ->when(is_array($publicationContent), fn ($query) => $query->whereIn('id', array_values(array_filter($publicationContent['professional_ids'] ?? [], 'is_string'))))
            ->with(['services' => fn ($query) => $query->select('services.id')->where('services.tenant_id', $tenant->getKey())->where('services.unit_id', $unit->getKey())])
            ->firstOrFail();

        $professionalServiceIds = $professional->services->pluck('id');
        if ($services->contains(fn (Service $candidate): bool => ! $professionalServiceIds->contains($candidate->getKey()))) {
            abort(404);
        }

        $timezone = (string) ($unit->timezone ?? $tenant->timezone ?? config('app.timezone'));
        $startsAt = CarbonImmutable::parse($data['starts_at'], $timezone)->setTimezone($timezone);
        $now = CarbonImmutable::now($timezone);

        $setting = $unit->onlineBookingSetting;
        $bookingPolicy = is_array($publicationContent['booking_policy'] ?? null) ? $publicationContent['booking_policy'] : [];
        $minimumNoticeMinutes = (int) ($bookingPolicy['minimum_notice_minutes'] ?? ($setting instanceof OnlineBookingSetting ? $setting->minimum_notice_minutes : 0));
        if ($startsAt->isBefore($now->addMinutes((int) $minimumNoticeMinutes)) || $startsAt->isAfter($now->addDays(31))) {
            throw ValidationException::withMessages(['starts_at' => 'The selected time is no longer available.']);
        }
        $hours = array_key_exists('public_hours', $publicationContent ?? [])
            ? (array) $publicationContent['public_hours']
            : (array) ($setting instanceof OnlineBookingSetting ? ($setting->getAttribute('public_hours') ?? []) : []);
        $window = $hours[(string) $startsAt->dayOfWeek] ?? $hours[$startsAt->dayOfWeek] ?? null;
        if (! is_array($window) || ($window['enabled'] ?? true) === false || ! isset($window['starts_at'], $window['ends_at'])) {
            throw ValidationException::withMessages(['starts_at' => 'The selected time is outside public booking hours.']);
        }
        $windowStart = CarbonImmutable::parse($startsAt->toDateString().' '.$window['starts_at'], $timezone);
        $windowEnd = CarbonImmutable::parse($startsAt->toDateString().' '.$window['ends_at'], $timezone);
        $offsetFromWindowStart = $startsAt->getTimestamp() - $windowStart->getTimestamp();
        if (
            $offsetFromWindowStart < 0
            || $offsetFromWindowStart % 900 !== 0
            || $startsAt->format('s.u') !== '00.000000'
            || $startsAt->addMinutes((int) $services->sum('duration_minutes'))->gt($windowEnd)
        ) {
            throw ValidationException::withMessages(['starts_at' => 'The selected time is outside public booking hours.']);
        }
        $duration = (int) $services->sum('duration_minutes');
        $endsAt = $startsAt->addMinutes($duration);
        $phone = $this->normalizePhone($data['phone']);
        $phoneCandidates = array_values(array_unique([
            $phone,
            Str::startsWith($phone, '55') ? Str::substr($phone, 2) : $phone,
        ]));
        $email = trim((string) ($data['email'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        $lockKey = sprintf('public-booking:%s:%s:%s', $tenant->getKey(), $unit->getKey(), $professional->getKey());

        return Cache::lock($lockKey, 10)->block(5, function () use ($tenant, $unit, $data, $services, $professional, $startsAt, $endsAt, $timezone, $phone, $phoneCandidates, $email, $notes): Appointment {
            return DB::transaction(function () use ($tenant, $unit, $data, $services, $professional, $startsAt, $endsAt, $timezone, $phone, $phoneCandidates, $email, $notes): Appointment {
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
                    ->where(function (Builder $query) use ($phoneCandidates): void {
                        $query->whereIn('phone_normalized', $phoneCandidates)
                            ->orWhereIn('phone', $phoneCandidates);
                    })
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
                            'email' => $email !== '' ? $email : null,
                            'phone' => $phone,
                            'phone_normalized' => $phone,
                            'status' => 'active',
                        ]), 1);
                    } catch (QueryException $exception) {
                        $customer = Customer::query()
                            ->whereBelongsTo($tenant)
                            ->whereBelongsTo($unit)
                            ->where(function (Builder $query) use ($phoneCandidates): void {
                                $query->whereIn('phone_normalized', $phoneCandidates)
                                    ->orWhereIn('phone', $phoneCandidates);
                            })
                            ->where('status', 'active')
                            ->lockForUpdate()
                            ->first();

                        if ($customer === null) {
                            throw $exception;
                        }
                    }
                } else {
                    $customer->update([
                        'name' => $data['name'],
                        'phone_normalized' => $phone,
                        ...($email !== '' ? ['email' => $email] : []),
                    ]);
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
                    'notes' => $notes !== '' ? $notes : null,
                    'lock_version' => 0,
                ]);

                foreach ($services as $position => $service) {
                    AppointmentItem::query()->create([
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenant->getKey(),
                        'unit_id' => $unit->getKey(),
                        'appointment_id' => $appointment->getKey(),
                        'service_id' => $service->getKey(),
                        'professional_id' => $professional->getKey(),
                        'service_name_snapshot' => $service->name,
                        'duration_minutes' => $service->duration_minutes,
                        'price_cents' => $service->price_cents,
                        'currency' => $tenant->default_currency,
                        'position' => $position + 1,
                    ]);
                }

                $this->createAppointmentSale->handlePublic($tenant, $unit, $appointment);
                $appointment->statusHistories()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->getKey(),
                    'unit_id' => $unit->getKey(),
                    'actor_user_id' => null,
                    'action' => 'created',
                    'from_status' => null,
                    'to_status' => $appointment->status,
                    'metadata' => ['source' => 'online'],
                    'occurred_at' => now(),
                ]);
                $this->events->recordForTenant(null, $tenant, 'appointment.created', $appointment, ['status' => $appointment->status], $unit->getKey());
                if ($this->confirmation->allowsCalendarSync($tenant)) {
                    SyncGoogleCalendarAppointment::dispatch((string) $appointment->getKey())->afterCommit();
                }

                return $appointment->fresh('items');
            }, 5);
        });
    }

    private function normalizePhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return strlen($digits) <= 11 ? '55'.$digits : $digits;
    }

    private function activePublication(Tenant $tenant, Unit $unit): ?OnlineBookingPublication
    {
        if (! config('online_booking.use_publication_resolver', true)) {
            return null;
        }

        return OnlineBookingSite::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->with('activePublication')
            ->first()
            ?->activePublication;
    }
}
