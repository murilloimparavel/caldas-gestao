<?php

namespace App\Actions\TenantDomains;

use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\EntitlementService;
use App\Support\HostnameNormalizer;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class CreateTenantDomain
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly EntitlementService $entitlements,
    ) {}

    public function handle(User $actor, TenantContext $context, string $hostname): TenantDomain
    {
        $this->authorization->assertTenantManager($actor, $context, $context->tenant);

        if (! $this->entitlements->can($context->tenant, 'domains.custom')) {
            throw new AuthorizationException('O tenant não possui acesso a domínios personalizados.');
        }

        return TenantDomain::query()->create([
            'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(),
            'hostname' => HostnameNormalizer::normalize($hostname), 'kind' => TenantDomainKind::Management,
            'status' => TenantDomainStatus::Pending, 'verification_token' => Str::random(64),
            'expected_cname' => config('domains.expected_cname', 'vps.caldasindica.com'),
            'ssl_status' => 'pending', 'metadata' => [],
        ]);
    }
}
