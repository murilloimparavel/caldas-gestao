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

        // 3. Appointments count & growth rate
        $totalAppointments = (clone $currentAppointmentsQuery)->count();
        $prevAppointments = (clone $prevAppointmentsQuery)->count();
        $growthRatePercentage = $this->calculateVariation($totalAppointments, $prevAppointments);

        // 4. Sales count & Conversion rate & Tickets (Comandas)
        $totalSalesCount = (clone $currentSalesQuery)->where('status', 'completed')->count();
        $prevSalesCount = (clone $prevSalesQuery)->where('status', 'completed')->count();
        $ticketsVariationPercentage = $this->calculateVariation($totalSalesCount, $prevSalesCount);

        $conversionRatePercentage = $totalAppointments > 0
            ? round(($totalSalesCount / $totalAppointments) * 100, 1)
            : 0.0;

        // 5. Sparkline data (daily counts/totals)
        $salesSparkline = $this->calculateSalesSparkline($tenant, $unit, $startDate, $endDate);
        $appointmentsSparkline = $this->calculateAppointmentsSparkline($tenant, $unit, $startDate, $endDate);
        $ticketsSparkline = $this->calculateTicketsSparkline($tenant, $unit, $startDate, $endDate);

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
        $visitsTrend = $this->calculateVisitsTrend($tenant, $unit, $startDate, $endDate);

        // 7. Status breakdown
        $statusBreakdown = $this->calculateStatusBreakdown((clone $currentAppointmentsQuery)->get(), $totalAppointments);

        // 8. Professionals performance
        $professionalPerformance = $this->calculateProfessionalsPerformance($tenant, $unit, $startDate, $endDate, $prevStartDate, $prevEndDate);

        // 9. Sales by category
        $salesCategoryBreakdown = $this->calculateSalesByCategory($tenant, $unit, $startDate, $endDate);

        // 10. Schedule heatmap
        $allPeriodAppointments = (clone $currentAppointmentsQuery)->get();
        $scheduleHeatmap = $this->calculateScheduleHeatmap($allPeriodAppointments);

        // 11. Today's Next 5 Appointments
        $nextAppointments = $this->calculateNextAppointments($tenant, $unit);

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
    private function calculateSalesSparkline(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $sales = Sale::query()
            ->selectRaw('DATE(created_at) as date_key, SUM(final_amount_cents) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date_key')
            ->pluck('aggregate', 'date_key');

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
    private function calculateAppointmentsSparkline(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $appointments = Appointment::query()
            ->selectRaw('DATE(starts_at) as date_key, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->groupBy('date_key')
            ->pluck('aggregate', 'date_key');

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
    private function calculateTicketsSparkline(Tenant $tenant, ?Unit $unit, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $tickets = Sale::query()
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as aggregate')
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date_key')
            ->pluck('aggregate', 'date_key');

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
     * @return list<array{category: string, label: string, totalAmount: string, percentage: float, color: string}>
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
    private function calculateScheduleHeatmap($appointments): array
    {
        $days = [
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
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
            /** @var CarbonInterface|null $startsAt */
            $startsAt = $appointment->starts_at;
            if (! $startsAt) {
                continue;
            }

            $dayOfWeek = (int) $startsAt->dayOfWeekIso;
            $hour = (int) $startsAt->hour;
            $key = "{$dayOfWeek}_{$hour}";

            if (isset($grid[$key])) {
                $grid[$key]['count']++;
            }
        }

        return array_values($grid);
    }

    /**
     * @return list<array{id: string, startsAt: string, client: string, service: string, professional: string, status: string}>
     */
    private function calculateNextAppointments(Tenant $tenant, ?Unit $unit): array
    {
        $todayStart = CarbonImmutable::now()->startOfDay();
        $todayEnd = CarbonImmutable::now()->endOfDay();

        $appointments = Appointment::query()
            ->with(['customer', 'professional', 'items.service'])
            ->where('tenant_id', $tenant->id)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->orderBy('starts_at', 'asc')
            ->limit(5)
            ->get();

        return array_values($appointments->map(function (Appointment $app) {
            /** @var CarbonInterface|null $startsAt */
            $startsAt = $app->starts_at;
            $firstItem = $app->items->first();

            return [
                'id' => $app->id,
                'startsAt' => $startsAt ? $startsAt->format('H:i') : '--:--',
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
        $openSalesCount = Sale::query()
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
