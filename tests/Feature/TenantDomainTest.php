<?php

use App\Actions\TenantDomains\ActivateTenantDomain;
use App\Actions\TenantDomains\DisableTenantDomain;
use App\Actions\TenantDomains\MarkTenantDomainSslVerified;
use App\Actions\TenantDomains\SuspendTenantDomain;
use App\Actions\TenantDomains\VerifyTenantDomain;
use App\Actions\TenantDomains\VerifyTenantDomainSsl;
use App\Contracts\CustomDomainProvisioner;
use App\Contracts\DnsResolver;
use App\Contracts\TlsCertificateVerifier;
use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\Entitlement;
use App\Models\TenantDomain;
use App\Support\HostnameNormalizer;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

it('normalizes valid hostnames', function (): void {
    expect(HostnameNormalizer::normalize(' Gestao.Cliente.Example.COM. '))
        ->toBe('gestao.cliente.example.com');
});

it('rejects unsafe hostname formats', function (string $hostname): void {
    HostnameNormalizer::normalize($hostname);
})->with([
    'cliente',
    'https://cliente.example.com',
    'cliente.example.com/painel',
    'gestao..cliente.com',
    '-gestao.cliente.com',
])->throws(InvalidArgumentException::class);

it('persists a tenant domain with its controlled state', function (): void {
    $domain = TenantDomain::factory()->create([
        'kind' => TenantDomainKind::Management,
        'status' => TenantDomainStatus::Verified,
    ]);

    expect($domain->id)->toBeString()
        ->and($domain->kind)->toBe(TenantDomainKind::Management)
        ->and($domain->status)->toBe(TenantDomainStatus::Verified)
        ->and($domain->tenant)->not->toBeNull();
});

it('does not allow a hostname to be claimed twice', function (): void {
    TenantDomain::factory()->create(['hostname' => 'gestao.cliente.example.com']);

    expect(fn () => TenantDomain::factory()->create(['hostname' => 'gestao.cliente.example.com']))
        ->toThrow(QueryException::class);
});

it('resolves an active custom hostname without changing the official domain', function (): void {
    $domain = TenantDomain::factory()->create([
        'hostname' => 'gestao.cliente.example.com',
        'status' => TenantDomainStatus::Active,
    ]);

    $response = $this->get('http://'.$domain->hostname.'/');

    $response->assertStatus(Response::HTTP_OK);
});

it('rejects an unknown custom hostname', function (): void {
    $this->get('http://gestao.desconhecido.example.com/')
        ->assertNotFound();
});

it('blocks a suspended custom hostname', function (): void {
    $domain = TenantDomain::factory()->create([
        'hostname' => 'gestao.suspenso.example.com',
        'status' => TenantDomainStatus::Suspended,
    ]);

    $this->get('http://'.$domain->hostname.'/')
        ->assertForbidden();
});

it('verifies matching CNAME and TXT records for a Premium tenant', function (): void {
    $domain = TenantDomain::factory()->create();
    Entitlement::factory()->create([
        'tenant_id' => $domain->tenant_id,
        'key' => 'domains.custom',
        'status' => EntitlementStatus::Active,
        'source' => EntitlementSource::Plan,
    ]);

    app()->instance(DnsResolver::class, new class implements DnsResolver
    {
        public function records(string $hostname, int $type): array
        {
            return $type === DNS_CNAME
                ? [['target' => 'vps.caldasindica.com.']]
                : [['txt' => 'unused'], ['txt' => 'verification-token']];
        }
    });
    $domain->update(['verification_token' => 'verification-token']);

    $verified = app(VerifyTenantDomain::class)->handle($domain);

    expect($verified->status)->toBe(TenantDomainStatus::Verified)
        ->and($verified->last_dns_error)->toBeNull()
        ->and($verified->verified_at)->not->toBeNull();
});

it('keeps a domain pending with a useful DNS diagnosis', function (): void {
    $domain = TenantDomain::factory()->create();
    Entitlement::factory()->create(['tenant_id' => $domain->tenant_id, 'key' => 'domains.custom']);

    app()->instance(DnsResolver::class, new class implements DnsResolver
    {
        public function records(string $hostname, int $type): array
        {
            return [];
        }
    });

    $checked = app(VerifyTenantDomain::class)->handle($domain);

    expect($checked->status)->toBe(TenantDomainStatus::Pending)
        ->and($checked->last_dns_error)->toBe('CNAME e TXT não encontrados ou incorretos.')
        ->and($checked->last_dns_check_at)->not->toBeNull();
});

it('does not activate a verified domain before SSL is active', function (): void {
    $domain = TenantDomain::factory()->create(['status' => TenantDomainStatus::Verified]);

    expect(fn () => app(ActivateTenantDomain::class)->handle($domain))
        ->toThrow(DomainException::class, 'certificado SSL');
});

it('activates a domain only after DNS verification and SSL validation', function (): void {
    $domain = TenantDomain::factory()->create(['status' => TenantDomainStatus::Verified]);
    app(MarkTenantDomainSslVerified::class)->handle($domain);

    $active = app(ActivateTenantDomain::class)->handle($domain);

    expect($active->status)->toBe(TenantDomainStatus::Active)
        ->and($active->activated_at)->not->toBeNull()
        ->and($active->ssl_verified_at)->not->toBeNull();
});

it('marks SSL active when the hostname certificate validates', function (): void {
    $domain = TenantDomain::factory()->create(['hostname' => 'gestao.cliente.example.com']);
    app()->instance(TlsCertificateVerifier::class, new class implements TlsCertificateVerifier
    {
        public function verify(string $hostname): ?string
        {
            return null;
        }
    });

    $checked = app(VerifyTenantDomainSsl::class)->handle($domain);

    expect($checked->ssl_status)->toBe('active')
        ->and($checked->ssl_verified_at)->not->toBeNull();
});

it('records an SSL error without activating the domain', function (): void {
    $domain = TenantDomain::factory()->create(['hostname' => 'gestao.cliente.example.com']);
    app()->instance(TlsCertificateVerifier::class, new class implements TlsCertificateVerifier
    {
        public function verify(string $hostname): ?string
        {
            return 'certificate mismatch';
        }
    });

    $checked = app(VerifyTenantDomainSsl::class)->handle($domain);

    expect($checked->ssl_status)->toBe('error')
        ->and($checked->status)->toBe(TenantDomainStatus::Pending)
        ->and($checked->last_dns_error)->toBe('certificate mismatch');
});

it('suspends a custom domain without affecting the official domain', function (): void {
    $domain = TenantDomain::factory()->create(['hostname' => 'gestao.suspenso.example.com', 'status' => TenantDomainStatus::Active]);
    app(SuspendTenantDomain::class)->handle($domain);

    $this->get('http://'.$domain->hostname.'/')->assertForbidden();
    $this->get('http://localhost/')->assertOk();
});

it('disables a custom domain and records the disable timestamp', function (): void {
    $domain = TenantDomain::factory()->create(['status' => TenantDomainStatus::Active]);

    $disabled = app(DisableTenantDomain::class)->handle($domain);

    expect($disabled->status)->toBe(TenantDomainStatus::Disabled)
        ->and($disabled->disabled_at)->not->toBeNull();
});

it('provisions a custom hostname without overwriting existing Coolify domains', function (): void {
    config()->set('services.coolify', [
        'url' => 'https://coolify.test',
        'token' => 'test-token',
        'application_uuid' => 'application-uuid',
    ]);
    $domain = TenantDomain::factory()->create(['status' => TenantDomainStatus::Verified]);
    Http::fake([
        'https://coolify.test/api/v1/applications/application-uuid' => Http::response(['fqdn' => "https://gestao.caldasindica.com\nhttps://legacy.example.com"]),
    ]);

    app(CustomDomainProvisioner::class)->provision($domain);

    Http::assertSent(function (Request $request) use ($domain): bool {
        $domains = (string) ($request->data()['domains'] ?? '');

        return $request->method() === 'PATCH'
            && str_contains($domains, 'https://gestao.caldasindica.com')
            && str_contains($domains, 'https://legacy.example.com')
            && str_contains($domains, 'https://'.$domain->hostname);
    });
});
