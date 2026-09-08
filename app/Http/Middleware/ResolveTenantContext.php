<?php

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\TenantDomain;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->identifier($request->header('X-Request-Id')) ?? (string) Str::uuid7();
        $correlationId = $this->identifier($request->header('X-Correlation-Id')) ?? $requestId;
        Context::add([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
        ]);
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        /** @var TenantDomain|null $tenantDomain */
        $tenantDomain = $request->attributes->get('tenant_domain');
        $explicitTenant = $tenantDomain->tenant_id
            ?? $request->session()->get('tenant_id')
            ?? $request->header('X-Tenant-Id')
            ?? $request->route('tenant');
        $explicitUnit = $request->session()->get('unit_id')
            ?? $request->header('X-Unit-Id')
            ?? $request->route('unit');
        $activeMemberships = Membership::query()
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active)
            ->count();

        // A newly authenticated identity may not have a workspace yet.
        // An explicit selection is still resolved and rejected when invalid.
        if ($explicitTenant === null && $explicitUnit === null && $activeMemberships === 0) {
            return $next($request);
        }

        $context = TenantContext::fromRequest($request);

        app()->instance(TenantContext::class, $context);
        Context::add([
            'tenant_id' => $context->tenant->getKey(),
            'membership_id' => $context->membership->getKey(),
            'unit_id' => $context->unit?->getKey(),
        ]);
        $request->attributes->set(TenantContext::class, $context);

        return $next($request);
    }

    private function identifier(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && (Str::isUuid($value) || Str::isUlid($value)) ? $value : null;
    }
}
