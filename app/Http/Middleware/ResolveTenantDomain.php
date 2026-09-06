<?php

namespace App\Http\Middleware;

use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use App\Support\HostnameNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawHostname = strtolower(trim($request->getHost()));
        $officialHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            ...((array) config('app.official_hosts', [])),
        ]);

        if (in_array($rawHostname, $officialHosts, true)) {
            return $next($request);
        }

        $hostname = HostnameNormalizer::normalize($rawHostname);

        $domain = TenantDomain::query()->where('hostname', $hostname)->first();

        if ($domain === null) {
            abort(404, 'Domínio não configurado.');
        }

        if ($domain->status === TenantDomainStatus::Suspended) {
            abort(403, 'Domínio suspenso.');
        }

        if ($domain->status !== TenantDomainStatus::Active) {
            abort(404, 'Domínio ainda não está ativo.');
        }

        $request->attributes->set('tenant_domain', $domain);

        return $next($request);
    }
}
