<?php

namespace App\Http\Controllers;

use App\Actions\Professionals\CreateProfessional;
use App\Actions\Professionals\DeactivateProfessional;
use App\Actions\Professionals\UpdateProfessional;
use App\Http\Requests\ProfessionalRequest;
use App\Models\Professional;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProfessionalController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Professional::class);
        $search = trim((string) $request->string('search'));
        $professionals = Professional::query()->with('services:id,name')->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('professionals/index', ['professionals' => $professionals, 'filters' => ['search' => $search]]);
    }

    public function show(Professional $professional): Response
    {
        Gate::authorize('view', $professional);

        return Inertia::render('professionals/show', ['professional' => $professional->load('services:id,name')]);
    }

    public function store(ProfessionalRequest $request, TenantContext $context, CreateProfessional $createProfessional): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createProfessional, $request, $context, $data): array {
            $professional = $createProfessional->handle($request->user(), $context, $data);

            return ['resource_id' => $professional->getKey(), 'resource_type' => 'professional'];
        });
        $professional = Professional::query()->findOrFail($reference['resource_id']);

        return to_route('professionals.show', $professional)->with('success', 'Profissional cadastrado.');
    }

    public function update(ProfessionalRequest $request, TenantContext $context, Professional $professional, UpdateProfessional $updateProfessional): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateProfessional, $request, $context, $professional, $data): array {
            $updated = $updateProfessional->handle($request->user(), $context, $professional, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'professional'];
        });
        $professional = Professional::query()->findOrFail($reference['resource_id']);

        return to_route('professionals.show', $professional)->with('success', 'Profissional atualizado.');
    }

    public function destroy(ProfessionalRequest $request, TenantContext $context, Professional $professional, DeactivateProfessional $deactivateProfessional): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateProfessional, $request, $context, $professional, $data): array {
            $deactivated = $deactivateProfessional->handle($request->user(), $context, $professional, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'professional'];
        });

        return to_route('professionals.index')->with('success', 'Profissional inativado.');
    }
}
