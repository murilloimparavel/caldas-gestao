<?php

namespace App\Actions\Dashboard;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardSnapshot
{
    /**
     * Execute the snapshot aggregation action.
     *
     * @param  array{preset?: string, start_date?: string, end_date?: string}  $filters
     * @return array<string, mixed>
     */
    public function handle(Tenant $tenant, ?Unit $unit = null, array $filters = []): array
    {
        $preset = $filters['preset'] ?? '30d';

        [$startDate, $endDate] = $this->resolveDateRange($preset, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        // Previous date range of equal duration
        $daysCount = $startDate->diffInDays($endDate) + 1;
        $prevEndDate = $startDate->subDay()->endOfDay();
        $prevStartDate = $prevEndDate->subDays($daysCount - 1)->startOfDay();

        // Query builders base
        $currentSalesQuery = Sale::query()
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('created_at', [$startDate, $endDate]);

        $prevSalesQuery = Sale::query()
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate]);

        $currentAppointmentsQuery = Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$startDate, $endDate]);

        $prevAppointmentsQuery = Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$prevStartDate, $prevEndDate]);

        // 1. Sales metrics
        $totalSalesCents = (int) (clone $currentSalesQuery)->where('status', 'completed')->sum('final_amount_cents');
        $prevTotalSalesCents = (int) (clone $prevSalesQuery)->where('status', 'completed')->sum('final_amount_cents');

        $todayStart = CarbonImmutable::now()->startOfDay();
        $todayEnd = CarbonImmutable::now()->endOfDay();
        $todaySalesCents = (int) Sale::query()
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'completed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('final_amount_cents');

        $salesVariationPercentage = $this->calculateVariation($totalSalesCents, $prevTotalSalesCents);

        // 2. Appointments count & growth rate
        $totalAppointments = (clone $currentAppointmentsQuery)->count();
        $prevAppointments = (clone $prevAppointmentsQuery)->count();
        $growthRatePercentage = $this->calculateVariation($totalAppointments, $prevAppointments);

        // 3. Sales count & Conversion rate
        $totalSalesCount = (clone $currentSalesQuery)->where('status', 'completed')->count();
        $conversionRatePercentage = $totalAppointments > 0
            ? round(($totalSalesCount / $totalAppointments) * 100, 1)
            : 0.0;

        // 4. Ticket Médio
        $ticketCurrentCents = $totalSalesCount > 0 ? (int) round($totalSalesCents / $totalSalesCount) : 0;
        $prevSalesCount = (clone $prevSalesQuery)->where('status', 'completed')->count();
        $ticketPrevCents = $prevSalesCount > 0 ? (int) round($prevTotalSalesCents / $prevSalesCount) : 0;
        $ticketVariationPercentage = $this->calculateVariation($ticketCurrentCents, $ticketPrevCents);

        // 5. Visits trend (daily breakdown)
        $visitsTrend = $this->calculateVisitsTrend($tenant, $unit, $startDate, $endDate);

        // 6. Status breakdown
        $statusBreakdown = $this->calculateStatusBreakdown((clone $currentAppointmentsQuery)->get(), $totalAppointments);

        // 7. Professionals performance
        $professionalsPerformance = $this->calculateProfessionalsPerformance($tenant, $unit, $startDate, $endDate, $prevStartDate, $prevEndDate);

        // 8. Sales by category
        $salesByCategory = $this->calculateSalesByCategory($tenant, $unit, $startDate, $endDate);

        // 9. Appointment funnel
        $allPeriodAppointments = (clone $currentAppointmentsQuery)->get();
        $appointmentFunnel = [
            'total' => $allPeriodAppointments->count(),
            'confirmed' => $allPeriodAppointments->whereIn('status', ['confirmed', 'completed'])->count(),
            'billed' => $totalSalesCount,
        ];

        // 10. Schedule heatmap
        $scheduleHeatmap = $this->calculateScheduleHeatmap($allPeriodAppointments);

        return [
            'period' => [
                'preset' => $preset,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'previous_start_date' => $prevStartDate->toDateString(),
                'previous_end_date' => $prevEndDate->toDateString(),
            ],
            'total_sales_cents' => $totalSalesCents,
            'today_sales_cents' => $todaySalesCents,
            'sales_variation_percentage' => $salesVariationPercentage,
            'total_appointments' => $totalAppointments,
            'growth_rate_percentage' => $growthRatePercentage,
            'total_sales_count' => $totalSalesCount,
            'conversion_rate_percentage' => $conversionRatePercentage,
            'visits_trend' => $visitsTrend,
            'status_breakdown' => $statusBreakdown,
            'ticket_medio' => [
                'current_cents' => $ticketCurrentCents,
                'previous_cents' => $ticketPrevCents,
                'variation_percentage' => $ticketVariationPercentage,
            ],
            'professionals_performance' => $professionalsPerformance,
            'sales_by_category' => $salesByCategory,
            'appointment_funnel' => $appointmentFunnel,
            'schedule_heatmap' => $scheduleHeatmap,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveDateRange(string $preset, ?string $startDateStr, ?string $endDateStr): array
    {
        $now = CarbonImmutable::now();

        return match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($startDateStr ?? $now->toDateString())->startOfDay(),
                CarbonImmutable::parse($endDateStr ?? $now->toDateString())->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()], // 30d
        };
    }

    private function calculateVariation(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array{date: string, label: string, visits: int, sales_cents: int}>
     */
    private function calculateVisitsTrend(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $appointments = Appointment::query()
            ->selectRaw('DATE(starts_at) as date_key, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->groupBy('date_key')
            ->pluck('aggregate', 'date_key');

        $sales = Sale::query()
            ->selectRaw('DATE(created_at) as date_key, SUM(final_amount_cents) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date_key')
            ->pluck('aggregate', 'date_key');

        $trend = [];
        $cursor = $startDate->startOfDay();

        while ($cursor->lte($endDate)) {
            $dateKey = $cursor->toDateString();
            $trend[] = [
                'date' => $dateKey,
                'label' => $cursor->format('d/m'),
                'visits' => (int) ($appointments[$dateKey] ?? 0),
                'sales_cents' => (int) ($sales[$dateKey] ?? 0),
            ];
            $cursor = $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{status: string, label: string, count: int, percentage: float, color: string}>
     */
    private function calculateStatusBreakdown($appointments, int $total): array
    {
        $statusConfig = [
            'confirmed' => ['label' => 'Confirmado', 'color' => '#3b82f6'],
            'completed' => ['label' => 'Concluído', 'color' => '#22c55e'],
            'cancelled' => ['label' => 'Cancelado', 'color' => '#ef4444'],
            'no_show' => ['label' => 'No-Show', 'color' => '#f59e0b'],
        ];

        $counts = $appointments->groupBy('status')->map->count();

        $result = [];
        foreach ($statusConfig as $statusKey => $cfg) {
            $count = (int) ($counts[$statusKey] ?? 0);
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

            $result[] = [
                'status' => $statusKey,
                'label' => $cfg['label'],
                'count' => $count,
                'percentage' => $percentage,
                'color' => $cfg['color'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{id: string, name: string, avatar_url: ?string, services_count: int, variation_percentage: float, average_ticket_cents: int}>
     */
    private function calculateProfessionalsPerformance(
        Tenant $tenant,
        ?Unit $unit,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        CarbonImmutable $prevStartDate,
        CarbonImmutable $prevEndDate
    ): array {
        $professionals = Professional::query()
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->get();

        $currentAppointmentsCount = Appointment::query()
            ->selectRaw('professional_id, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->groupBy('professional_id')
            ->pluck('aggregate', 'professional_id');

        $prevAppointmentsCount = Appointment::query()
            ->selectRaw('professional_id, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$prevStartDate, $prevEndDate])
            ->groupBy('professional_id')
            ->pluck('aggregate', 'professional_id');

        // Sales totals per professional from SaleItems
        $currentSalesByProf = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sale_items.professional_id, SUM(sale_items.total_cents) as total_cents')
            ->where('sales.tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('sales.unit_id', $unit->id))
            ->where('sales.status', 'completed')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->whereNotNull('sale_items.professional_id')
            ->groupBy('sale_items.professional_id')
            ->pluck('total_cents', 'sale_items.professional_id');

        $result = [];

        foreach ($professionals as $prof) {
            $currCount = (int) ($currentAppointmentsCount[$prof->id] ?? 0);
            $prevCount = (int) ($prevAppointmentsCount[$prof->id] ?? 0);
            $variation = $this->calculateVariation($currCount, $prevCount);

            $salesTotalCents = (int) ($currentSalesByProf[$prof->id] ?? 0);
            $avgTicketCents = $currCount > 0 ? (int) round($salesTotalCents / $currCount) : 0;

            $result[] = [
                'id' => $prof->id,
                'name' => $prof->name,
                'avatar_url' => $prof->avatar_url,
                'services_count' => $currCount,
                'variation_percentage' => $variation,
                'average_ticket_cents' => $avgTicketCents,
            ];
        }

        // Sort by services_count desc
        usort($result, fn ($a, $b) => $b['services_count'] <=> $a['services_count']);

        return $result;
    }

    /**
     * @return array{services: array{total_cents: int, percentage: float}, products: array{total_cents: int, percentage: float}, packages: array{total_cents: int, percentage: float}}
     */
    private function calculateSalesByCategory(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $items = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sale_items.item_type, SUM(sale_items.total_cents) as total_cents')
            ->where('sales.tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('sales.unit_id', $unit->id))
            ->where('sales.status', 'completed')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->groupBy('sale_items.item_type')
            ->pluck('total_cents', 'sale_items.item_type');

        $servicesCents = (int) ($items['service'] ?? 0);
        $productsCents = (int) ($items['product'] ?? 0);
        $packagesCents = (int) ($items['package'] ?? 0);

        $totalCents = $servicesCents + $productsCents + $packagesCents;

        return [
            'services' => [
                'total_cents' => $servicesCents,
                'percentage' => $totalCents > 0 ? round(($servicesCents / $totalCents) * 100, 1) : 0.0,
            ],
            'products' => [
                'total_cents' => $productsCents,
                'percentage' => $totalCents > 0 ? round(($productsCents / $totalCents) * 100, 1) : 0.0,
            ],
            'packages' => [
                'total_cents' => $packagesCents,
                'percentage' => $totalCents > 0 ? round(($packagesCents / $totalCents) * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{day_of_week: int, day_label: string, hours: list<array{hour: int, label: string, count: int}>}>
     */
    private function calculateScheduleHeatmap($appointments): array
    {
        // Days of week: 1 (Monday) to 6 (Saturday)
        $days = [
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
        ];

        // Hours: 8 to 19
        $grid = [];

        foreach ($days as $dayOfWeek => $dayLabel) {
            $hours = [];
            for ($h = 8; $h <= 19; $h++) {
                $hours[$h] = 0;
            }
            $grid[$dayOfWeek] = [
                'day_of_week' => $dayOfWeek,
                'day_label' => $dayLabel,
                'hours' => $hours,
            ];
        }

        foreach ($appointments as $appointment) {
            /** @var CarbonInterface|null $startsAt */
            $startsAt = $appointment->starts_at;
            if (! $startsAt) {
                continue;
            }

            $dayOfWeek = (int) $startsAt->dayOfWeekIso; // 1 = Mon, 7 = Sun
            $hour = (int) $startsAt->hour;

            if (isset($grid[$dayOfWeek]) && isset($grid[$dayOfWeek]['hours'][$hour])) {
                $grid[$dayOfWeek]['hours'][$hour]++;
            }
        }

        $formattedHeatmap = [];
        foreach ($grid as $dayOfWeek => $dayData) {
            $hoursList = [];
            foreach ($dayData['hours'] as $hour => $count) {
                $hoursList[] = [
                    'hour' => $hour,
                    'label' => sprintf('%02d:00', $hour),
                    'count' => $count,
                ];
            }

            $formattedHeatmap[] = [
                'day_of_week' => $dayOfWeek,
                'day_label' => $dayData['day_label'],
                'hours' => $hoursList,
            ];
        }

        return $formattedHeatmap;
    }
}
