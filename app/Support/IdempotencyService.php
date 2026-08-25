<?php

namespace App\Support;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class IdempotencyService
{
    public function __construct(private readonly PayloadGovernance $payload = new PayloadGovernance) {}

    /**
     * Execute a tenant-scoped command once and replay its stored response on retry.
     *
     * @param  Closure(): mixed  $operation
     */
    public function execute(
        Tenant|string $tenant,
        User|string|null $actor,
        string $key,
        mixed $request,
        Closure $operation,
        ?DateTimeInterface $expiresAt = null,
    ): IdempotencyResult {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : $tenant;
        $actorId = $actor instanceof User ? (string) $actor->getKey() : $actor;
        $requestHash = $this->hash($request);
        $expiresAt ??= now()->addDay();

        try {
            return DB::transaction(function () use ($tenantId, $actorId, $key, $requestHash, $request, $operation, $expiresAt): IdempotencyResult {
                $record = $this->lockOrCreate($tenantId, $actorId, $key, $requestHash, $expiresAt);

                if ($record->request_hash !== $requestHash) {
                    throw new ConflictHttpException('The idempotency key was already used with a different request payload.');
                }

                $this->assertReplayScope($record, $request);

                if ($record->status !== IdempotencyStatus::Started && ! $record->isExpired()) {
                    return new IdempotencyResult(
                        value: $record->response_ref,
                        replayed: true,
                        key: $record,
                        responseCode: (int) ($record->response_code ?? 200),
                    );
                }

                if ($record->isExpired()) {
                    $record->forceFill([
                        'status' => IdempotencyStatus::Started,
                        'expires_at' => $expiresAt,
                        'completed_at' => null,
                        'response_code' => null,
                        'response_ref' => null,
                    ])->save();
                }

                try {
                    $value = DB::transaction($operation, 1);
                } catch (Throwable $exception) {
                    throw new IdempotencyOperationFailed($exception);
                }
                $responseCode = 200;
                $responseValue = $value;

                if (is_array($value) && array_key_exists('value', $value)) {
                    $responseCode = (int) ($value['response_code'] ?? 200);
                    $responseValue = $value['value'];
                }
                $responseRef = $this->responseReference($responseValue);

                $record->forceFill([
                    'status' => IdempotencyStatus::Succeeded,
                    'response_code' => $responseCode,
                    'response_ref' => $responseRef,
                    'resource_type' => is_string($responseRef['resource_type'] ?? null) ? $responseRef['resource_type'] : null,
                    'resource_id' => is_scalar($responseRef['resource_id'] ?? null) ? (string) $responseRef['resource_id'] : null,
                    'completed_at' => now(),
                ])->save();

                return new IdempotencyResult($value, false, $record->fresh(), $responseCode);
            }, 5);
        } catch (IdempotencyOperationFailed $failure) {
            $this->persistFailure($tenantId, $actorId, $key, $requestHash, $expiresAt);

            throw $failure->operationException;
        }
    }

    private function lockOrCreate(string $tenantId, ?string $actorId, string $key, string $requestHash, DateTimeInterface $expiresAt): IdempotencyKey
    {
        $record = IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->when($actorId === null, static fn ($query) => $query->whereNull('actor_user_id'))
            ->when($actorId !== null, static fn ($query) => $query->where('actor_user_id', $actorId))
            ->lockForUpdate()
            ->first();

        if ($record !== null) {
            return $record;
        }

        try {
            IdempotencyKey::query()->insertOrIgnore([
                'tenant_id' => $tenantId,
                'actor_user_id' => $actorId,
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => IdempotencyStatus::Started->value,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            // A concurrent insert is resolved by the locked read below.
        }

        return IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->when($actorId === null, static fn ($query) => $query->whereNull('actor_user_id'))
            ->when($actorId !== null, static fn ($query) => $query->where('actor_user_id', $actorId))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function hash(mixed $request): string
    {
        try {
            return hash('sha256', json_encode($this->canonicalize($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('The idempotency request payload must be JSON serializable.', 0, $exception);
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->canonicalize($item);
            }

            if (! $isList) {
                ksort($normalized, SORT_STRING);
            }

            return $normalized;
        }

        if (is_object($value) || is_resource($value)) {
            throw new \InvalidArgumentException('The idempotency request must contain JSON arrays and scalar values only.');
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function responseReference(mixed $value): array
    {
        if (is_array($value)) {
            return $this->payload->responseReference($value);
        }

        if (is_scalar($value) || $value === null) {
            return ['status' => is_scalar($value) ? (string) $value : null];
        }

        throw new \InvalidArgumentException('The idempotency response reference cannot contain an object.');
    }

    private function persistFailure(string $tenantId, ?string $actorId, string $key, string $requestHash, DateTimeInterface $expiresAt): void
    {
        DB::transaction(function () use ($tenantId, $actorId, $key, $requestHash, $expiresAt): void {
            $record = $this->lockOrCreate($tenantId, $actorId, $key, $requestHash, $expiresAt);

            if ($record->request_hash !== $requestHash) {
                throw new ConflictHttpException('The idempotency key was already used with a different request payload.');
            }

            if ($record->status === IdempotencyStatus::Succeeded) {
                return;
            }

            $record->forceFill([
                'status' => IdempotencyStatus::Failed,
                'response_code' => 500,
                'response_ref' => ['status' => 'failed'],
                'expires_at' => $expiresAt,
                'completed_at' => now(),
            ])->save();
        }, 5);
    }

    private function assertReplayScope(IdempotencyKey $record, mixed $request): void
    {
        if (! is_array($request) || ! is_array($request['scope'] ?? null)) {
            return;
        }

        $scope = $request['scope'];
        $resourceType = $scope['resource_type'] ?? null;
        $resourceId = $scope['resource_id'] ?? null;

        if ($record->resource_type !== null && $record->resource_type !== $resourceType) {
            throw new ConflictHttpException('The idempotency key cannot be replayed for a different resource type.');
        }

        if ($record->resource_id !== null && $resourceId !== null && $record->resource_id !== $resourceId) {
            throw new ConflictHttpException('The idempotency key cannot be replayed for a different resource.');
        }
    }
}
