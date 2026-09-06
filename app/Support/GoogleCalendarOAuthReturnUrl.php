<?php

namespace App\Support;

use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class GoogleCalendarOAuthReturnUrl
{
    /** @return array{return_host:string,return_path:string} */
    public function capture(Request $request, TenantContext $context): array
    {
        $host = $this->normalizeHost($request->getHost());

        if (! in_array($host, $this->officialHosts(), true) && ! TenantDomain::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('hostname', $host)
            ->where('status', TenantDomainStatus::Active)
            ->where('kind', 'management')
            ->exists()) {
            throw new InvalidArgumentException('The Google Calendar return host is not an active domain for this tenant.');
        }

        $path = '/calendar';
        $referer = $request->headers->get('referer');

        if (is_string($referer) && $referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $refererPath = parse_url($referer, PHP_URL_PATH);

            try {
                if (is_string($refererHost) && is_string($refererPath) && $this->normalizeHost($refererHost) === $host) {
                    $path = $refererPath;
                }
            } catch (InvalidArgumentException) {
                $path = '/calendar';
            }
        }

        if (! in_array($path, (array) config('services.google_calendar.return_paths', ['/calendar']), true)) {
            throw new InvalidArgumentException('The Google Calendar return path is not allowed.');
        }

        return ['return_host' => $host, 'return_path' => $path];
    }

    public function url(string $host, string $path, ?string $tenantId = null): string
    {
        try {
            $host = $this->normalizeHost($host);
        } catch (InvalidArgumentException) {
            return $this->officialUrl('/calendar');
        }

        if (! in_array($host, $this->officialHosts(), true)) {
            $domain = TenantDomain::query()
                ->where('hostname', $host)
                ->where('status', TenantDomainStatus::Active)
                ->where('kind', 'management');

            if ($tenantId !== null) {
                $domain->where('tenant_id', $tenantId);
            }

            if (! $domain->exists()) {
                return $this->officialUrl('/calendar');
            }
        }

        if (! in_array($path, (array) config('services.google_calendar.return_paths', ['/calendar']), true)) {
            return $this->officialUrl('/calendar');
        }

        return $this->scheme().'://'.$host.$path;
    }

    public function isOfficialHost(string $host): bool
    {
        try {
            return in_array($this->normalizeHost($host), $this->officialHosts(), true);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function officialUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    /** @return list<string> */
    private function officialHosts(): array
    {
        $hosts = [
            parse_url((string) config('app.url'), PHP_URL_HOST),
            ...((array) config('app.official_hosts', [])),
            ...((array) config('domains.official_hosts', [])),
        ];

        if (config('app.env') !== 'production') {
            $hosts = [...$hosts, 'localhost', '127.0.0.1'];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): ?string => is_string($host) && $host !== '' ? $this->normalizeHost($host) : null,
            $hosts,
        ))));
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $host;
        }

        return HostnameNormalizer::normalize($host);
    }

    private function scheme(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https');
    }
}
