<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Retention\BuildRetentionCampaignAudience;
use App\Actions\Marketing\Retention\CreateRetentionCampaign;
use App\Actions\Marketing\Retention\DispatchRetentionCampaign;
use App\Actions\Marketing\Retention\ProcessRetentionCampaignDeliveries;
use App\Actions\Marketing\Retention\UpdateRetentionCampaignStatus;
use App\Http\Requests\CreateRetentionCampaignRequest;
use App\Http\Requests\RetentionCampaignOperationRequest;
use App\Http\Requests\UpdateRetentionCampaignStatusRequest;
use App\Models\RetentionCampaign;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class RetentionCampaignController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(TenantContext $context): Response
    {
        Gate::authorize('viewAny', RetentionCampaign::class);

        return Inertia::render('retention/campaigns', ['campaigns' => RetentionCampaign::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->withCount(['recipients', 'deliveries'])->latest()->paginate(25)->withQueryString()]);
    }

    public function store(CreateRetentionCampaignRequest $request, TenantContext $context, CreateRetentionCampaign $action): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($action, $request, $context, $data): array {
            $campaign = $action->handle($request->user(), $context, $data);

            return ['resource_id' => $campaign->getKey(), 'resource_type' => 'retention_campaign'];
        });
        $campaign = RetentionCampaign::query()->findOrFail($reference['resource_id']);

        return $request->wantsJson() ? response()->json(['campaign' => $campaign], 201) : to_route('retention.campaigns.index')->with('success', 'Campanha criada.');
    }

    public function status(UpdateRetentionCampaignStatusRequest $request, TenantContext $context, RetentionCampaign $retentionCampaign, UpdateRetentionCampaignStatus $action): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($action, $request, $context, $retentionCampaign, $data): array {
            $campaign = $action->handle($request->user(), $context, $retentionCampaign, $data['status']);

            return ['resource_id' => $campaign->getKey(), 'resource_type' => 'retention_campaign'];
        });
        $campaign = RetentionCampaign::query()->findOrFail($reference['resource_id']);

        return $request->wantsJson() ? response()->json(['campaign' => $campaign]) : back()->with('success', 'Status da campanha atualizado.');
    }

    public function audience(RetentionCampaignOperationRequest $request, TenantContext $context, RetentionCampaign $retentionCampaign, BuildRetentionCampaignAudience $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $context, $retentionCampaign, $request->boolean('dry_run')));
    }

    public function dispatch(RetentionCampaignOperationRequest $request, TenantContext $context, RetentionCampaign $retentionCampaign, DispatchRetentionCampaign $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $context, $retentionCampaign, $request->boolean('dry_run')));
    }

    public function process(RetentionCampaignOperationRequest $request, TenantContext $context, RetentionCampaign $retentionCampaign, ProcessRetentionCampaignDeliveries $action): JsonResponse
    {
        return response()->json($action->handle(
            $request->user(),
            $context,
            $retentionCampaign,
            $request->boolean('dry_run'),
            $request->integer('limit', 100),
        ));
    }
}
