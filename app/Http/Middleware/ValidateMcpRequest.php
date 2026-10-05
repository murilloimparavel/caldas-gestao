<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateMcpRequest
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->contentType($request) !== 'application/json') {
            return response()->json([
                'error' => 'unsupported_media_type',
                'message' => 'MCP requests must use application/json.',
            ], 415);
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null && $this->origin($origin) !== $this->origin((string) config('app.url'))) {
            return response()->json([
                'error' => 'invalid_origin',
                'message' => 'The MCP request origin is not allowed.',
            ], 403);
        }

        return $next($request);
    }

    private function contentType(Request $request): string
    {
        return strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'), 2)[0]));
    }

    private function origin(string $value): ?string
    {
        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || array_intersect(['user', 'pass', 'path', 'query', 'fragment'], array_keys($parts)) !== []) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme.'://'.$host.($port === null ? '' : ':'.$port);
    }
}
