<?php

namespace App\Actions\Dashboard;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardSnapshot
{
    private ?string $professionalId = null;

    /**
     * Execute the snapshot aggregation action.
     *
     * @param  array{preset?: string, start_date?: string, end_date?: string}  $filters
     * @return array<string, mixed>
     */
    public function handle(Tenant $tenant, ?Unit $unit = null, array $filters = [], ?string $professionalId = null): array
    {
        $this->professionalId = $professionalId;
        $preset = $filters['preset'] ?? '30d';

        $timezone = $unit?->timezone ?: $tenant->timezone ?: config('app.timezone');
        [$startDate, $endDate] = $this->resolveDateRange($preset, $filters['start_date'] ?? null, $filters['end_date'] ?? null, $timezone);

        // Previous date range of equal duration
        $daysCount = $startDate->diffInDays($endDate) + 1;
        $prevEndDate = $startDate->subDay()->endOfDay();
        $prevStartDate = $prevEndDate->subDays($daysCount - 1)->startOfDay();

        // Query builders base
        $currentSalesQuery = $this->constrainSales(Sale::query())
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('created_at', $this->utcDateRange($startDate, $endDate));

        $prevSalesQuery = $this->constrainSales(Sale::query())
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('created_at', $this->utcDateRange($prevStartDate, $prevEndDate));

        $currentAppointmentsQuery = $this->constrainAppointments(Appointment::query())
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', $this->utcDateRange($startDate, $endDate));

        $prevAppointmentsQuery = $this->constrainAppointments(Appointment::query())
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', $this->utcDateRange($prevStartDate, $prevEndDate));

        // 1. User Name
        $userName = auth()->user()?->name;
        if (! $userName) {
            $ownerMembership = $tenant->memberships()
                ->whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                ->with('user')
                ->first();

            $userName = $ownerMembership?->user->name ?? 'Usuário';
        }

        // 2. Sales metrics
        $totalSalesCents = (int) (clone $currentSalesQuery)->where('status', 'finalized')->sum('final_amount_cents');
        $prevTotalSalesCents = (int) (clone $prevSalesQuery)->where('status', 'finalized')->sum('final_amount_cents');

        $todayStart = CarbonImmutable::now($timezone)->startOfDay();
        $todayEnd = CarbonImmutable::now($timezone)->endOfDay();
        $todaySalesCents = (int) $this->constrainSales(Sale::query())
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'finalized')
            ->whereBetween('created_at', $this->utcDateRange($todayStart, $todayEnd))
            ->sum('final_amount_cents');

        $salesVariationPercentage = $this->calculateVariation($totalSalesCents, $prevTotalSalesCents);

        // 3. Appointments count & growth rate
        $totalAppointments = (clone $currentAppointmentsQuery)->count();
        $prevAppointments = (clone $prevAppointmentsQuery)->count();
        $growthRatePercentage = $this->calculateVariation($totalAppointments, $prevAppointments);

        // 4. Sales count & Conversion rate & Tickets (Comandas)
        $totalSalesCount = (clone $currentSalesQuery)->where('status', 'finalized')->count();
        $prevSalesCount = (clone $prevSalesQuery)->where('status', 'finalized')->count();
        $ticketsVariationPercentage = $this->calculateVariation($totalSalesCount, $prevSalesCount);

        $conversionRatePercentage = $totalAppointments > 0
            ? round(($totalSalesCount / $totalAppointments) * 100, 1)
            : 0.0;

        // 5. Sparkline data (daily counts/totals)
        $salesSparkline = $this->calculateSalesSparkline($tenant, $unit, $startDate, $endDate, $timezone);
        $appointmentsSparkline = $this->calculateAppointmentsSparkline($tenant, $unit, $startDate, $endDate, $timezone);
        $ticketsSparkline = $this->calculateTicketsSparkline($tenant, $unit, $startDate, $endDate, $timezone);

        // Top KPIs
        $topKpis = [
            'totalSales' => [
                'title' => 'Vendas Totais',
                'value' => $this->formatCurrency($totalSalesCents),
                'changePercentage' => $salesVariationPercentage,
                'trend' => $this->getTrend($salesVariationPercentage),
                'sparklineData' => $salesSparkline,
                'todayValue' => $this->formatCurrency($todaySalesCents),
            ],
            'appointments' => [
                'title' => 'Agendamentos',
                'value' => (string) $totalAppointments,
                'changePercentage' => $growthRatePercentage,
                'trend' => $this->getTrend($growthRatePercentage),
                'sparklineData' => $appointmentsSparkline,
            ],
            'tickets' => [
                'title' => 'Comandas',
                'value' => (string) $totalSalesCount,
                'changePercentage' => $ticketsVariationPercentage,
                'trend' => $this->getTrend($ticketsVariationPercentage),
                'sparklineData' => $ticketsSparkline,
                'conversionRate' => $conversionRatePercentage,
            ],
        ];

        // 6. Visits trend (daily breakdown)
        $visitsTrend = $this->calculateVisitsTrend($tenant, $unit, $startDate, $endDate, $timezone);

        // 7. Status breakdown
        $statusBreakdown = $this->calculateStatusBreakdown((clone $currentAppointmentsQuery)->get(), $totalAppointments);

        // 8. Professionals performance
        $professionalPerformance = $this->calculateProfessionalsPerformance($tenant, $unit, $startDate, $endDate, $prevStartDate, $prevEndDate);

        // 9. Sales by category
        $salesCategoryBreakdown = $this->calculateSalesByCategory($tenant, $unit, $startDate, $endDate);

        // 10. Schedule heatmap
        $allPeriodAppointments = (clone $currentAppointmentsQuery)->get();
        $scheduleHeatmap = $this->calculateScheduleHeatmap($allPeriodAppointments, $timezone);

        // 11. Today's Next 5 Appointments
        $nextAppointments = $this->calculateNextAppointments($tenant, $unit, $timezone);

        // 12. Attention Items (alerts)
        $attentionItems = $this->calculateAttentionItems($tenant, $unit);

        return [
            'period' => [
                'preset' => $preset,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'previousStartDate' => $prevStartDate->toDateString(),
                'previousEndDate' => $prevEndDate->toDateString(),
            ],
            'userName' => $userName,
            'topKpis' => $topKpis,
            'visitsTrend' => $visitsTrend,
            'statusBreakdown' => $statusBreakdown,
            'professionalPerformance' => $professionalPerformance,
            'salesCategoryBreakdown' => $salesCategoryBreakdown,
            'scheduleHeatmap' => $scheduleHeatmap,
            'appointments' => $nextAppointments,
            'attentionItems' => $attentionItems,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveDateRange(string $preset, ?string $startDateStr, ?string $endDateStr, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        return match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth()->startOfDay(), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($startDateStr ?? $now->toDateString(), $timezone)->startOfDay(),
                CarbonImmutable::parse($endDateStr ?? $now->toDateString(), $timezone)->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()], // 30d
        };
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    private function constrainSales(Builder $query): Builder
    {
        if ($this->professionalId === null) {
            return $query;
        }

        $professionalId = $this->professionalId;

        return $query->where(function (Builder $saleQuery) use ($professionalId): void {
            $saleQuery
                ->where('professional_id', $professionalId)
                ->orWhereHas('items', function (Builder $itemsQuery) use ($professionalId): void {
                    $itemsQuery->where(function (Builder $query) use ($professionalId): void {
                        $query->where('professional_id', $professionalId)
                            ->orWhere('seller_professional_id', $professionalId);
                    });
                })
                ->orWhereHas('appointmentLink.appointment', fn (Builder $appointmentQuery) => $appointmentQuery->where('professional_id', $professionalId));
        });
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private function constrainAppointments(Builder $query): Builder
    {
        return $this->professionalId === null
            ? $query
            : $query->where('professional_id', $this->professionalId);
    }

    private function calculateVariation(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function utcDateRange(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        return [$startDate->utc(), $endDate->utc()];
    }

    private function formatCurrency(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    private function getTrend(float $variation): string
    {
        if ($variation > 0) {
            return 'up';
        }
        if ($variation < 0) {
            return 'down';
        }

        return 'neutral';
    }

    /**
     * @return list<int>
     */
    private function calculateSalesSparkline(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate, string $timezone): array
    {
        $sales = $this->constrainSales(Sale::query())
            ->select(['created_at', 'final_amount_cents'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'finalized')
            ->whereBetween('created_at', $this->utcDateRange($startDate, $endDate))
            ->get()
            ->groupBy(fn (Sale $sale): string => $sale->created_at->setTimezone($timezone)->toDateString())
            ->map(fn (Collection $daySales): int => (int) $daySales->sum('final_amount_cents'));

        $data = [];
        $cursor = $startDate->startOfDay();
        while ($cursor->lte($endDate)) {
            $dateKey = $cursor->toDateString();
            $data[] = (int) ($sales[$dateKey] ?? 0);
            $cursor = $cursor->addDay();
        }

        return $data;
    }

    /**
     * @return list<int>
     */
    private function calculateAppointmentsSparkline(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate, string $timezone): array
    {
        $appointments = $this->constrainAppointments(Appointment::query())
            ->select(['starts_at'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', $this->utcDateRange($startDate, $endDate))
            ->get()
            ->groupBy(fn (Appointment $appointment): string => CarbonImmutable::parse($appointment->starts_at)->setTimezone($timezone)->toDateString())
            ->map->count();

        $data = [];
        $cursor = $startDate->startOfDay();
        while ($cursor->lte($endDate)) {
            $dateKey = $cursor->toDateString();
            $data[] = (int) ($appointments[$dateKey] ?? 0);
            $cursor = $cursor->addDay();
        }

        return $data;
    }

    /**
     * @return list<int>
     */
    private function calculateTicketsSparkline(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate, string $timezone): array
    {
        $tickets = $this->constrainSales(Sale::query())
            ->select(['created_at'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'finalized')
            ->whereBetween('created_at', $this->utcDateRange($startDate, $endDate))
            ->get()
            ->groupBy(fn (Sale $sale): string => $sale->created_at->setTimezone($timezone)->toDateString())
            ->map->count();

        $data = [];
        $cursor = $startDate->startOfDay();
        while ($cursor->lte($endDate)) {
            $dateKey = $cursor->toDateString();
            $data[] = (int) ($tickets[$dateKey] ?? 0);
            $cursor = $cursor->addDay();
        }

        return $data;
    }

    /**
     * @return list<array{date: string, label: string, visits: int, salesCents: int}>
     */
    private function calculateVisitsTrend(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate, string $timezone): array
    {
        $appointments = $this->constrainAppointments(Appointment::query())
            ->select(['starts_at'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', $this->utcDateRange($startDate, $endDate))
            ->whereIn('status', ['checked_in', 'in_service', 'completed'])
            ->get()
            ->groupBy(fn (Appointment $appointment): string => CarbonImmutable::parse($appointment->starts_at)->setTimezone($timezone)->toDateString())
            ->map->count();

        $sales = $this->constrainSales(Sale::query())
            ->select(['created_at', 'final_amount_cents'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'finalized')
            ->whereBetween('created_at', $this->utcDateRange($startDate, $endDate))
            ->get()
            ->groupBy(fn (Sale $sale): string => $sale->created_at->setTimezone($timezone)->toDateString())
            ->map(fn (Collection $daySales): int => (int) $daySales->sum('final_amount_cents'));

        $trend = [];
        $cursor = $startDate->startOfDay();

        while ($cursor->lte($endDate)) {
            $dateKey = $cursor->toDateString();
            $trend[] = [
                'date' => $dateKey,
                'label' => $cursor->format('d/m'),
                'visits' => (int) ($appointments[$dateKey] ?? 0),
                'salesCents' => (int) ($sales[$dateKey] ?? 0),
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
            'draft' => ['label' => 'Rascunho', 'color' => '#94a3b8'],
            'scheduled' => ['label' => 'Agendado', 'color' => '#6366f1'],
            'confirmed' => ['label' => 'Confirmado', 'color' => '#3b82f6'],
            'checked_in' => ['label' => 'Em espera', 'color' => '#06b6d4'],
            'in_service' => ['label' => 'Em atendimento', 'color' => '#8b5cf6'],
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
     * @return list<array{id: string, name: string, avatarUrl: ?string, totalServices: int, changePercentage: float, averageTicket: string}>
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
            ->when($this->professionalId !== null, fn ($q) => $q->whereKey($this->professionalId))
            ->get();

        $currentAppointmentsCount = $this->constrainAppointments(Appointment::query())
            ->selectRaw('professional_id, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', $this->utcDateRange($startDate, $endDate))
            ->groupBy('professional_id')
            ->pluck('aggregate', 'professional_id');

        $prevAppointmentsCount = $this->constrainAppointments(Appointment::query())
            ->selectRaw('professional_id, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', $this->utcDateRange($prevStartDate, $prevEndDate))
            ->groupBy('professional_id')
            ->pluck('aggregate', 'professional_id');

        $currentSalesByProf = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sale_items.professional_id, SUM(sale_items.total_cents) as total_cents')
            ->where('sales.tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('sales.unit_id', $unit->id))
            ->where('sales.status', 'finalized')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->when($this->professionalId !== null, fn ($q) => $q->where(function ($query): void {
                $query->where('sales.professional_id', $this->professionalId)
                    ->orWhere('sale_items.professional_id', $this->professionalId)
                    ->orWhere('sale_items.seller_professional_id', $this->professionalId);
            }))
            ->whereBetween('sales.created_at', $this->utcDateRange($startDate, $endDate))
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
                'avatarUrl' => $prof->avatar_url,
                'totalServices' => $currCount,
                'changePercentage' => $variation,
                'averageTicket' => $this->formatCurrency($avgTicketCents),
            ];
        }

        usort($result, fn ($a, $b) => $b['totalServices'] <=> $a['totalServices']);

        return $result;
    }

    /**
     * Allocate each finalized sale's net amount across its item categories.
     * This keeps the category cards mathematically consistent with totalSales.
     *
     * @return list<array{category: string, label: string, totalAmount: string, percentage: float, color: string}>
     */
    private function calculateSalesByCategory(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $items = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->select(['sale_items.sale_id', 'sale_items.item_type', 'sale_items.total_cents', 'sales.final_amount_cents'])
            ->where('sales.tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('sales.unit_id', $unit->id))
            ->where('sales.status', 'finalized')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->when($this->professionalId !== null, fn ($q) => $q->where(function ($query): void {
                $query->where('sales.professional_id', $this->professionalId)
                    ->orWhere('sale_items.professional_id', $this->professionalId)
                    ->orWhere('sale_items.seller_professional_id', $this->professionalId);
            }))
            ->whereBetween('sales.created_at', $this->utcDateRange($startDate, $endDate))
            ->get();

        $categoryTotals = ['service' => 0, 'product' => 0, 'package' => 0];
        foreach ($items->groupBy('sale_id') as $saleItems) {
            $finalAmount = (int) $saleItems->first()->final_amount_cents;
            $categoryItems = $saleItems->filter(fn ($item): bool => array_key_exists((string) $item->item_type, $categoryTotals))->values();
            $grossAmount = (int) $categoryItems->sum('total_cents');
            if ($grossAmount <= 0) {
                continue;
            }

            $allocated = 0;
            foreach ($categoryItems as $index => $item) {
                $type = (string) $item->item_type;
                $share = $index === $categoryItems->count() - 1
                    ? $finalAmount - $allocated
                    : (int) round($finalAmount * ((int) $item->total_cents / $grossAmount));
                $categoryTotals[$type] += $share;
                $allocated += $share;
            }
        }

        $servicesCents = $categoryTotals['service'];
        $productsCents = $categoryTotals['product'];
        $packagesCents = $categoryTotals['package'];

        $totalCents = $servicesCents + $productsCents + $packagesCents;

        return [
            [
                'category' => 'services',
                'label' => 'Serviços',
                'totalAmount' => $this->formatCurrency($servicesCents),
                'percentage' => $totalCents > 0 ? round(($servicesCents / $totalCents) * 100, 1) : 0.0,
                'color' => '#3b82f6',
            ],
            [
                'category' => 'products',
                'label' => 'Produtos',
                'totalAmount' => $this->formatCurrency($productsCents),
                'percentage' => $totalCents > 0 ? round(($productsCents / $totalCents) * 100, 1) : 0.0,
                'color' => '#10b981',
            ],
            [
                'category' => 'packages',
                'label' => 'Pacotes',
                'totalAmount' => $this->formatCurrency($packagesCents),
                'percentage' => $totalCents > 0 ? round(($packagesCents / $totalCents) * 100, 1) : 0.0,
                'color' => '#8b5cf6',
            ],
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{dayOfWeek: int, dayLabel: string, hour: int, label: string, count: int}>
     */
    private function calculateScheduleHeatmap($appointments, string $timezone): array
    {
        $days = [
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        $grid = [];
        foreach ($days as $dayOfWeek => $dayLabel) {
            for ($h = 8; $h <= 19; $h++) {
                $grid["{$dayOfWeek}_{$h}"] = [
                    'dayOfWeek' => $dayOfWeek,
                    'dayLabel' => $dayLabel,
                    'hour' => $h,
                    'label' => sprintf('%02d:00', $h),
                    'count' => 0,
                ];
            }
        }

        foreach ($appointments as $appointment) {
            if (in_array($appointment->status, ['cancelled', 'no_show'], true)) {
                continue;
            }

            /** @var CarbonInterface|null $startsAt */
            $startsAt = $appointment->starts_at;
            if (! $startsAt) {
                continue;
            }

            $startsAt = $startsAt->setTimezone($timezone);
            $dayOfWeek = (int) $startsAt->dayOfWeekIso;
            /** @var CarbonInterface|null $endsAt */
            $endsAt = $appointment->ends_at;
            $end = $endsAt && $endsAt->greaterThan($startsAt)
                ? $endsAt
                : $startsAt->addHour();

            for ($hour = 8; $hour <= 19; $hour++) {
                $slotStart = $startsAt->copy()->startOfDay()->addHours($hour);
                $slotEnd = $slotStart->addHour();
                if ($startsAt->lt($slotEnd) && $end->gt($slotStart)) {
                    $key = "{$dayOfWeek}_{$hour}";
                    if (isset($grid[$key])) {
                        $grid[$key]['count']++;
                    }
                }
            }
        }

        return array_values($grid);
    }

    /**
     * @return list<array{id: string, startsAt: string, client: string, service: string, professional: string, status: string}>
     */
    private function calculateNextAppointments(Tenant $tenant, ?Unit $unit, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        $appointments = $this->constrainAppointments(Appointment::query())
            ->with(['customer', 'professional', 'items.service'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('starts_at', '>=', $now->utc())
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_service'])
            ->orderBy('starts_at', 'asc')
            ->limit(5)
            ->get();

        return array_values($appointments->map(function (Appointment $app) use ($timezone) {
            /** @var CarbonInterface|null $startsAt */
            $startsAt = $app->starts_at;
            $firstItem = $app->items->first();

            return [
                'id' => $app->id,
                'startsAt' => $startsAt ? $startsAt->setTimezone($timezone)->format('H:i') : '--:--',
                'client' => $app->customer->name ?? 'Cliente sem nome',
                'service' => $firstItem?->service->name ?? 'Serviço',
                'professional' => $app->professional->name ?? 'Profissional',
                'status' => $app->status,
            ];
        })->all());
    }

    /**
     * @return list<array{id: string, label: string, detail: string, level: string}>
     */
    private function calculateAttentionItems(Tenant $tenant, ?Unit $unit): array
    {
        $items = [];

        // 1. Overdue bills (FinancialObligations overdue)
        $overdueCount = DB::table('financial_obligations')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->where('due_date', '<', CarbonImmutable::now()->toDateString())
            ->count();

        if ($this->professionalId !== null) {
            $overdueCount = 0;
        }

        if ($overdueCount > 0) {
            $items[] = [
                'id' => 'overdue_bills',
                'label' => 'Contas vencidas',
                'detail' => "{$overdueCount} conta(s) a pagar estão vencida(s)",
                'level' => 'high',
            ];
        }

        // 2. Open draft sales created more than 4 hours ago
        $fourHoursAgo = CarbonImmutable::now()->subHours(4);
        $openSalesCount = $this->constrainSales(Sale::query())
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'draft')
            ->where('created_at', '<=', $fourHoursAgo)
            ->count();

        if ($openSalesCount > 0) {
            $items[] = [
                'id' => 'open_sales',
                'label' => 'Comandas abertas há mais de 4h',
                'detail' => "{$openSalesCount} comanda(s) aberta(s) necessitam fechamento",
                'level' => 'medium',
            ];
        }

        return $items;
    }
}
