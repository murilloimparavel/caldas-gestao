<?php

namespace App\Support\Integrations;

use Illuminate\Http\Request;

final class OAuthResource
{
    public static function mcp(): string
    {
        return app(OAuthEndpointConfiguration::class)->resource();
    }

    public static function fromRequest(Request $request): ?string
    {
        $resource = $request->input('resource') ?? $request->query('resource');

        return is_string($resource) && $resource !== '' ? rtrim($resource, '/') : null;
    }
}
