<?php

namespace App\Support\Integrations;

use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class OAuthEndpointConfiguration
{
    public function authorizationServer(): string
    {
        $configured = config('mcp.authorization_server');
        $url = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : trim((string) config('app.url'));

        return $this->httpsUrl($url, 'OAuth authorization server');
    }

    public function resource(): string
    {
        $configured = config('integration.oauth.resource');
        $url = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : $this->authorizationServer().'/mcp/integration';

        return $this->httpsUrl($url, 'OAuth protected resource');
    }

    public function endpoint(string $routeName): string
    {
        $relativePath = route($routeName, absolute: false);

        return rtrim($this->authorizationServer(), '/').'/'.ltrim($relativePath, '/');
    }

    private function httpsUrl(string $value, string $name): string
    {
        $parts = parse_url($value);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

        if (! is_array($parts)
            || $scheme !== 'https'
            || ! is_string($host)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new ServiceUnavailableHttpException(null, $name.' must be configured as a canonical HTTPS URL.');
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return 'https://'.Str::lower($host).$port.$path;
    }
}
