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
        $sharedBookingHost = strtolower(trim((string) config('domains.shared_booking_host')));

        if ($sharedBookingHost !== '' && $rawHostname === $sharedBookingHost) {
            if (! $this->isPublicBookingPath($request)) {
                abort(404, 'Rota não disponível neste domínio.');
            }

            $request->attributes->set('public_booking_shared_host', true);

            return $next($request);
        }

        $officialHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            ...((array) config('app.official_hosts', [])),
            ...((array) config('domains.official_hosts', [])),
            'localhost',
            '127.0.0.1',
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

    private function isPublicBookingPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === '' || str_starts_with($path, 'book/')) {
            return true;
        }

        return $request->route()?->getName() === 'public_booking.shared_slug';
    }
}
