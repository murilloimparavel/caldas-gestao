<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardSnapshot;
use App\Http\Requests\DashboardRequest;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, GetDashboardSnapshot $getDashboardSnapshot, TenantContext $context): Response
    {
        $tenant = $context->tenant;
        $unit = $context->unit;

        $filters = $request->validated();

        $snapshot = $getDashboardSnapshot->handle($tenant, $unit, $filters);

        return Inertia::render('dashboard', [
            'dashboard' => $snapshot,
            'filters' => [
                'preset' => $filters['preset'] ?? '30d',
                'start_date' => $filters['start_date'] ?? null,
                'end_date' => $filters['end_date'] ?? null,
            ],
        ]);
    }
}
