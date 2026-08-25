<?php

namespace App\Http\Controllers;

use App\Actions\Services\CreateService;
use App\Actions\Services\DeactivateService;
use App\Actions\Services\UpdateService;
use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Service::class);
        $search = trim((string) $request->string('search'));
        $services = Service::query()->with('professionals:id,name')->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('services/index', ['services' => $services, 'filters' => ['search' => $search]]);
    }

    public function show(Service $service): Response
    {
        Gate::authorize('view', $service);

        return Inertia::render('services/show', ['service' => $service->load('professionals:id,name')]);
    }

    public function store(ServiceRequest $request, TenantContext $context, CreateService $createService): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createService, $request, $context, $data): array {
            $service = $createService->handle($request->user(), $context, $data);

            return ['resource_id' => $service->getKey(), 'resource_type' => 'service'];
        });
        $service = Service::query()->findOrFail($reference['resource_id']);

        return to_route('services.show', $service)->with('success', 'Serviço cadastrado.');
    }

    public function update(ServiceRequest $request, TenantContext $context, Service $service, UpdateService $updateService): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateService, $request, $context, $service, $data): array {
            $updated = $updateService->handle($request->user(), $context, $service, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'service'];
        });
        $service = Service::query()->findOrFail($reference['resource_id']);

        return to_route('services.show', $service)->with('success', 'Serviço atualizado.');
    }

    public function destroy(ServiceRequest $request, TenantContext $context, Service $service, DeactivateService $deactivateService): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateService, $request, $context, $service, $data): array {
            $deactivated = $deactivateService->handle($request->user(), $context, $service, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'service'];
        });

        return to_route('services.index')->with('success', 'Serviço inativado.');
    }
}
