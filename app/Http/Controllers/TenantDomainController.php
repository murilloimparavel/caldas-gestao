<?php

namespace App\Http\Controllers;

use App\Actions\TenantDomains\ActivateTenantDomain;
use App\Actions\TenantDomains\CreateTenantDomain;
use App\Actions\TenantDomains\ProvisionTenantDomain;
use App\Actions\TenantDomains\VerifyTenantDomain;
use App\Actions\TenantDomains\VerifyTenantDomainSsl;
use App\Http\Requests\TenantDomainStoreRequest;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantDomainController extends Controller
{
    public function index(TenantContext $context): Response
    {
        return Inertia::render('settings/domains', ['domains' => $context->tenant->domains()->latest()->get(), 'expectedCname' => config('domains.expected_cname', 'vps.caldasindica.com')]);
    }

    public function store(TenantDomainStoreRequest $request, TenantContext $context, CreateTenantDomain $create): RedirectResponse
    {
        $create->handle($request->user(), $context, $request->validated('hostname'));

        return to_route('tenant-domains.index')->with('success', 'Domínio cadastrado. Configure o DNS para iniciar a verificação.');
    }

    public function verify(string $tenantDomain, TenantContext $context, VerifyTenantDomain $verify): RedirectResponse
    {
        $domain = $context->tenant->domains()->whereKey($tenantDomain)->firstOrFail();
        $verify->handle($domain);

        return to_route('tenant-domains.index')->with('success', 'Verificação DNS concluída.');
    }

    public function provision(string $tenantDomain, TenantContext $context, ProvisionTenantDomain $provision): RedirectResponse
    {
        $domain = $context->tenant->domains()->whereKey($tenantDomain)->firstOrFail();
        $provision->handle($domain);

        return to_route('tenant-domains.index')->with('success', 'Domínio enviado para o Coolify. Aguarde a emissão do SSL.');
    }

    public function activate(string $tenantDomain, TenantContext $context, VerifyTenantDomainSsl $verifySsl, ActivateTenantDomain $activate): RedirectResponse
    {
        $domain = $context->tenant->domains()->whereKey($tenantDomain)->firstOrFail();
        $verifySsl->handle($domain);
        $activate->handle($domain->refresh());

        return to_route('tenant-domains.index')->with('success', 'Domínio ativado com SSL.');
    }
}
