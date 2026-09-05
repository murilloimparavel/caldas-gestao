<?php

namespace App\Http\Middleware;

use App\Enums\UnitStatus;
use App\Models\Entitlement;
use App\Models\Unit;
use App\Support\AuthorizationService;
use App\Support\EntitlementService;
use App\Support\PayloadGovernance;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $context = $request->attributes->get(TenantContext::class);
        $context = $context instanceof TenantContext ? $context : null;
        $explicitTenant = $request->session()->get('tenant_id')
            ?? $request->header('X-Tenant-Id')
            ?? $request->route('tenant');
        $explicitUnit = $request->session()->get('unit_id')
            ?? $request->header('X-Unit-Id')
            ?? $request->route('unit');

        if ($context === null && $user !== null) {
            try {
                $context = TenantContext::fromRequest($request);
            } catch (AuthorizationException) {
                if ($explicitTenant !== null || $explicitUnit !== null) {
                    throw new AuthorizationException('The selected tenant context is not available to this user.');
                }
            }
        }
        $requestId = $this->identifier($request->header('X-Request-Id')) ?? (string) Str::uuid7();
        $correlationId = $this->identifier($request->header('X-Correlation-Id')) ?? $requestId;
        $permissions = $user !== null && $context !== null
            ? app(AuthorizationService::class)->permissions($user, $context)
            : [];
        $entitlements = $context === null
            ? []
            : app(EntitlementService::class)->activeFor($context->tenant)->map(fn (Entitlement $entitlement): array => [
                'id' => (string) $entitlement->getKey(),
                'key' => $entitlement->key,
                'status' => $entitlement->status->value,
                'quantity' => $entitlement->quantity,
                'source' => $entitlement->source->value,
                'starts_at' => $entitlement->starts_at->toISOString(),
                'ends_at' => $entitlement->ends_at === null ? null : $entitlement->ends_at->toISOString(),
                'metadata' => app(PayloadGovernance::class)->entitlementMetadata($entitlement->config),
            ])->values()->all();

        $availableUnits = [];

        if ($context !== null) {
            foreach ($context->membership->membershipUnits as $membershipUnit) {
                if ($membershipUnit->unit instanceof Unit && $membershipUnit->unit->status === UnitStatus::Active) {
                    $availableUnits[] = $this->unitSummary($membershipUnit->unit);
                }
            }
        }

        $workspace = $context === null ? null : [
            'tenant' => [
                'id' => (string) $context->tenant->getKey(),
                'name' => $context->tenant->name,
                'slug' => $context->tenant->slug,
                'status' => $context->tenant->status->value,
                'timezone' => $context->tenant->timezone,
                'default_currency' => $context->tenant->default_currency,
                'branding' => [
                    'name' => $context->tenant->brand_name ?: $context->tenant->name,
                    'logoUrl' => $context->tenant->logo_url,
                    'faviconUrl' => $context->tenant->favicon_url,
                    'primaryColor' => $context->tenant->primary_color,
                    'accentColor' => $context->tenant->accent_color,
                ],
            ],
            'activeUnit' => $context->unit === null ? null : $this->unitSummary($context->unit),
            'availableUnits' => $availableUnits,
        ];

        return [
            ...parent::share($request),
            'schemaVersion' => 1,
            'requestId' => $requestId,
            'correlationId' => $correlationId,
            'name' => config('app.name'),
            'branding' => [
                'name' => config('branding.name', config('app.name')),
                'logoUrl' => config('branding.logo_url'),
                'faviconUrl' => config('branding.favicon_url'),
                'primaryColor' => config('branding.primary_color'),
                'accentColor' => config('branding.accent_color'),
            ],
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                ],
                'permissions' => $permissions,
                'entitlements' => $entitlements,
            ],
            'workspace' => $workspace,
            'flash' => [
                'success' => $request->session()->get('success'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
            'ui' => [
                'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ],
            // Kept during the contract transition for existing shell consumers.
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array{id:string,name:string,slug:string,status:string,timezone:?string} */
    private function unitSummary(Unit $unit): array
    {
        return [
            'id' => (string) $unit->getKey(),
            'name' => $unit->name,
            'slug' => $unit->slug,
            'status' => $unit->status->value,
            'timezone' => $unit->timezone,
        ];
    }

    private function identifier(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && (Str::isUuid($value) || Str::isUlid($value)) ? $value : null;
    }
}
