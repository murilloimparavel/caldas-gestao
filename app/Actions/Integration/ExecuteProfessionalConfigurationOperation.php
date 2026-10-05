<?php

namespace App\Actions\Integration;

use App\Actions\Professionals\CreateProfessional;
use App\Actions\Professionals\UpdateProfessional;
use App\Models\Professional;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Executes the professional setup commands after proposal confirmation.
 *
 * The coordinator owns passkey confirmation, expiry, idempotency and result
 * redaction. This action keeps the command contract narrow and delegates the
 * actual mutation to the existing tenant and unit aware professional actions.
 */
final class ExecuteProfessionalConfigurationOperation
{
    public const PROFESSIONAL_CREATE = 'professional.create';

    public const PROFESSIONAL_UPDATE = 'professional.update';

    /** @var list<string> */
    private const FIELDS = ['name', 'status', 'service_ids'];

    public function __construct(
        private readonly CreateProfessional $createProfessional,
        private readonly UpdateProfessional $updateProfessional,
    ) {}

    /** @return list<string> */
    public static function operationKeys(): array
    {
        return [self::PROFESSIONAL_CREATE, self::PROFESSIONAL_UPDATE];
    }

    /** @param array<string, mixed> $payload */
    public function handle(User $actor, TenantContext $context, string $operation, array $payload): Professional
    {
        $this->validatePayload($operation, $payload);

        if ($operation === self::PROFESSIONAL_CREATE) {
            return $this->createProfessional->handle($actor, $context, $payload);
        }

        if ($operation !== self::PROFESSIONAL_UPDATE) {
            throw ValidationException::withMessages([
                'operation' => 'Esta operação de configuração não é suportada.',
            ]);
        }

        $professional = Professional::query()
            ->whereKey($payload['professional_id'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->first();

        if (! $professional instanceof Professional) {
            throw new AuthorizationException('The professional does not belong to the active workspace.');
        }

        $data = $payload;
        unset($data['professional_id'], $data['expected_version']);

        return $this->updateProfessional->handle(
            $actor,
            $context,
            $professional,
            $data,
            (int) $payload['expected_version'],
        );
    }

    /** @param array<string, mixed> $payload */
    private function validatePayload(string $operation, array $payload): void
    {
        if (! in_array($operation, self::operationKeys(), true)) {
            throw ValidationException::withMessages([
                'operation' => 'Esta operação de configuração não é suportada.',
            ]);
        }

        $isUpdate = $operation === self::PROFESSIONAL_UPDATE;
        $allowed = [...self::FIELDS, ...($isUpdate ? ['professional_id', 'expected_version'] : [])];
        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw ValidationException::withMessages([
                'input' => 'O payload contém campos não permitidos.',
            ]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'status' => ['required', 'in:active,inactive'],
            'service_ids' => ['sometimes', 'array', 'max:100'],
            'service_ids.*' => ['uuid', 'distinct'],
        ];

        if ($isUpdate) {
            $rules['professional_id'] = ['required', 'uuid'];
            $rules['expected_version'] = ['required', 'integer', 'min:0'];
        }

        Validator::make($payload, $rules)->validate();
    }
}
