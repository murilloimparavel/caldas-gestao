<?php

namespace App\Http\Controllers;

use App\Actions\Professionals\CreateProfessional;
use App\Actions\Professionals\DeactivateProfessional;
use App\Actions\Professionals\ReactivateProfessional;
use App\Actions\Professionals\UpdateProfessional;
use App\Http\Requests\ProfessionalNotificationMembershipRequest;
use App\Http\Requests\ProfessionalRequest;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProfessionalController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Professional::class);
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status', 'active');
        $professionals = Professional::query()
            ->with('services:id,name')
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($status === 'active', fn ($query) => $query->where('status', 'active'))
            ->when($status === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('professionals/index', [
            'professionals' => $professionals,
            'hasServices' => $this->hasServices($context),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Professional $professional, TenantContext $context): Response
    {
        Gate::authorize('view', $professional);

        $professional->load([
            'services:id,name',
            'availabilityRules' => fn ($query) => $query->orderBy('weekday')->orderBy('starts_at'),
            'scheduleBlocks' => fn ($query) => $query->where('status', 'active')->orderBy('starts_at'),
        ]);
        $serviceOptions = $professional->services
            ->map(fn (Service $service): array => [
                'id' => (string) $service->getKey(),
                'name' => $service->name,
            ])
            ->values()
            ->all();

        return Inertia::render('professionals/show', [
            'professional' => $professional,
            'serviceOptions' => $serviceOptions,
            'hasServiceOptions' => $this->hasServices($context) || $serviceOptions !== [],
            'canManageNotificationLink' => app(AuthorizationService::class)->can($context->user, $context, 'tenant.manage') && Gate::allows('update', $professional),
            'notificationMemberships' => app(AuthorizationService::class)->can($context->user, $context, 'tenant.manage') && Gate::allows('update', $professional)
                ? Membership::query()->with('user:id,name,email')->where('tenant_id', $context->tenant->id)->where('status', 'active')
                    ->whereHas('membershipUnits', fn ($query) => $query->where('unit_id', $professional->unit_id))
                    ->get()->map(fn (Membership $membership): array => ['id' => $membership->id, 'name' => $membership->user->name, 'email' => $membership->user->email])->values()
                : [],
        ]);
    }

    public function notificationMembership(ProfessionalNotificationMembershipRequest $request, Professional $professional, TenantContext $context): RedirectResponse
    {
        $membershipId = $request->validated('membership_id');
        DB::transaction(function () use ($membershipId, $professional, $context): void {
            $locked = Professional::query()->where('tenant_id', $context->tenant->id)->where('unit_id', $context->unit?->id)->whereKey($professional->id)->lockForUpdate()->firstOrFail();
            if ($membershipId !== null) {
                $membership = Membership::query()->where('tenant_id', $context->tenant->id)->whereKey($membershipId)->where('status', 'active')
                    ->whereHas('membershipUnits', fn ($query) => $query->where('unit_id', $professional->unit_id))->lockForUpdate()->first();
                if ($membership === null || Professional::query()->where('tenant_id', $context->tenant->id)->where('unit_id', $professional->unit_id)->where('notification_membership_id', $membershipId)->whereKeyNot($professional->id)->exists()) {
                    throw ValidationException::withMessages(['membership_id' => 'Selecione um usuário ativo desta unidade que ainda não esteja vinculado a outro profissional.']);
                }
            }
            $locked->forceFill(['notification_membership_id' => $membershipId, 'lock_version' => $locked->lock_version + 1])->save();
            app(IdentityEventRecorder::class)->record($context->user, $context, 'professional.notification_membership_updated', $locked, ['membership_id' => $membershipId]);
        });

        return back()->with('success', 'Usuário de notificações atualizado.');
    }

    private function hasServices(TenantContext $context): bool
    {
        return $context->unit === null
            ? false
            : Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit->getKey())
                ->where('status', 'active')
                ->exists();
    }

    public function store(ProfessionalRequest $request, TenantContext $context, CreateProfessional $createProfessional): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createProfessional, $request, $context, $data): array {
            $professional = $createProfessional->handle($request->user(), $context, $data);

            return ['resource_id' => $professional->getKey(), 'resource_type' => 'professional'];
        });
        $professional = Professional::query()->findOrFail($reference['resource_id']);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $professional->id,
                'name' => $professional->name,
                'professional' => $professional,
            ], 201);
        }

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

    public function reactivate(ProfessionalRequest $request, TenantContext $context, Professional $professional, ReactivateProfessional $reactivateProfessional): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivateProfessional, $request, $context, $professional, $data): array {
            $reactivated = $reactivateProfessional->handle($request->user(), $context, $professional, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'professional'];
        });

        return to_route('professionals.show', $professional)->with('success', 'Profissional reativado.');
    }
}
