<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

final class OperationalMutation
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    /**
     * Execute a mutating operation and optionally persist a replayable resource reference.
     *
     * @param  array<string, mixed>  $requestPayload
     * @param  Closure(): array{resource_id:string,resource_type:string}  $operation
     * @return array{resource_id:string,resource_type:string}
     */
    public function execute(Request $request, TenantContext $context, User $actor, array $requestPayload, Closure $operation): array
    {
        $key = trim((string) $request->header('X-Idempotency-Key', ''));

        if ($key === '') {
            return $operation();
        }

        if (mb_strlen($key) > 200) {
            throw new \InvalidArgumentException('The X-Idempotency-Key header must be at most 200 characters.');
        }

        $result = $this->idempotency->execute(
            $context->tenant,
            $actor,
            $key,
            [
                'scope' => $this->scope($request, $context),
                'payload' => $requestPayload,
            ],
            fn (): array => [
                'value' => $operation(),
                'response_code' => 303,
            ],
        );

        $reference = $result->value;

        if (is_array($reference) && array_key_exists('value', $reference)) {
            $reference = $reference['value'];
        }

        if (! is_array($reference) || ! isset($reference['resource_id'], $reference['resource_type'])) {
            throw new \LogicException('The idempotent operation returned an invalid resource reference.');
        }

        return [
            'resource_id' => (string) $reference['resource_id'],
            'resource_type' => (string) $reference['resource_type'],
        ];
    }

    /** @return array{command:string,method:string,route:string,resource_type:string,resource_id:string|null,unit_id:string|null} */
    private function scope(Request $request, TenantContext $context): array
    {
        $route = $request->route();
        $routeName = $route instanceof Route ? (string) ($route->getName() ?? '') : '';
        $routeUri = $route instanceof Route ? $route->uri() : $request->path();
        $resourceType = Str::singular(Str::before($routeName, '.'));
        $resource = $route instanceof Route ? $route->parameter($resourceType) : null;

        $resourceId = match (true) {
            $resource instanceof Model => (string) $resource->getKey(),
            is_scalar($resource) => (string) $resource,
            default => null,
        };

        return [
            'command' => $routeName !== '' ? $routeName : $request->path(),
            'method' => strtoupper($request->method()),
            'route' => $routeUri,
            'resource_type' => $resourceType !== '' ? $resourceType : 'operational',
            'resource_id' => $resourceId,
            'unit_id' => $context->unit?->getKey(),
        ];
    }
}
