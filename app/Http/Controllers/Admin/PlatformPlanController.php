<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlatformPlanRequest;
use App\Models\PlatformPlan;
use App\Models\User;
use App\Support\AuditEventWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/plans', ['plans' => PlatformPlan::query()->withCount('subscriptions')->orderBy('price_cents')->get()]);
    }

    public function store(PlatformPlanRequest $request, AuditEventWriter $audit): RedirectResponse
    {
        DB::transaction(function () use ($audit, $request): void {
            $plan = PlatformPlan::query()->create($request->validated());
            $this->audit($audit, $request->user(), 'platform.plan.created', $plan);
        });

        return to_route('admin.plans.index')->with('success', 'Plano criado.');
    }

    public function update(PlatformPlanRequest $request, PlatformPlan $platformPlan, AuditEventWriter $audit): RedirectResponse
    {
        DB::transaction(function () use ($audit, $platformPlan, $request): void {
            $platformPlan = PlatformPlan::query()->whereKey($platformPlan->getKey())->lockForUpdate()->firstOrFail();
            $platformPlan->update($request->validated());
            $this->audit($audit, $request->user(), 'platform.plan.updated', $platformPlan, ['key' => $platformPlan->key, 'price_cents' => $platformPlan->price_cents, 'billing_cycle' => $platformPlan->billing_cycle, 'is_active' => $platformPlan->is_active]);
        });

        return back()->with('success', 'Plano atualizado.');
    }

    public function deactivate(Request $request, PlatformPlan $platformPlan, AuditEventWriter $audit): RedirectResponse
    {
        DB::transaction(function () use ($audit, $platformPlan, $request): void {
            $platformPlan = PlatformPlan::query()->whereKey($platformPlan->getKey())->lockForUpdate()->firstOrFail();
            $platformPlan->update(['is_active' => false]);
            $this->audit($audit, $request->user(), 'platform.plan.deactivated', $platformPlan);
        });

        return back()->with('success', 'Plano desativado.');
    }

    public function reactivate(Request $request, PlatformPlan $platformPlan, AuditEventWriter $audit): RedirectResponse
    {
        DB::transaction(function () use ($audit, $platformPlan, $request): void {
            $platformPlan = PlatformPlan::query()->whereKey($platformPlan->getKey())->lockForUpdate()->firstOrFail();
            $platformPlan->update(['is_active' => true]);
            $this->audit($audit, $request->user(), 'platform.plan.reactivated', $platformPlan);
        });

        return back()->with('success', 'Plano reativado.');
    }

    /** @param array<string, mixed> $metadata */
    private function audit(AuditEventWriter $audit, ?User $actor, string $action, PlatformPlan $plan, array $metadata = []): void
    {
        $audit->record(['actor_user_id' => $actor?->getKey(), 'action' => $action, 'resource_type' => 'platform_plan', 'resource_id' => $plan->getKey(), 'metadata' => $metadata]);
    }
}
