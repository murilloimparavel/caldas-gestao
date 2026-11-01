<?php

namespace App\Support\Telemetry;

use App\Models\TelemetryIncident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IncidentWorkflow
{
    public function transition(string $id, string $status, User $actor, ?string $ownerId, int $expectedRevision, string $reason): TelemetryIncident
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Platform administration is required.');
        }
        if (! Str::isUuid($id) || ($ownerId !== null && ! Str::isUuid($ownerId))) {
            throw ValidationException::withMessages(['incident' => 'Identificador inválido.']);
        }
        if (! array_key_exists($status, config('telemetry.workflow_statuses', [])) || ! array_key_exists($reason, config('telemetry.workflow_reasons', []))) {
            throw ValidationException::withMessages(['workflow_status' => 'Estado ou motivo inválido.']);
        }
        if ($ownerId !== null && ! User::query()->whereKey($ownerId)->first()?->isSuperAdmin()) {
            throw ValidationException::withMessages(['owner_user_id' => 'Selecione um administrador da plataforma.']);
        }

        return DB::transaction(function () use ($id, $status, $actor, $ownerId, $expectedRevision, $reason): TelemetryIncident {
            $incident = TelemetryIncident::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($incident->lock_version !== $expectedRevision) {
                throw new ConflictHttpException('O incidente foi alterado em outra sessão.');
            }
            $from = $incident->workflow_status;
            $sameStatus = $status === $from;
            $allowed = config('telemetry.workflow_transitions.'.$from, []);
            $expectedReason = match ($status) {
                'triaged' => in_array($from, ['resolved', 'ignored'], true) ? ['reopened'] : ['triage_confirmed'],
                'in_analysis' => ['investigation_started'],
                'proposal_ready' => ['proposal_recorded'],
                'in_validation' => ['validation_started'],
                'resolved' => ['fix_verified'],
                'ignored' => ['duplicate', 'not_actionable'],
                default => [],
            };
            if (($sameStatus && ($reason !== 'owner_assigned' || $incident->owner_user_id === $ownerId)) || (! $sameStatus && (! in_array($status, $allowed, true) || ! in_array($reason, $expectedReason, true)))) {
                throw ValidationException::withMessages(['workflow_status' => 'Transição não permitida para o estado e motivo informados.']);
            }
            $incident->forceFill(['workflow_status' => $status, 'owner_user_id' => $ownerId, 'lock_version' => $incident->lock_version + 1, 'resolved_at' => $status === 'resolved' ? now() : null])->save();
            IncidentHistory::record($incident, 'workflow_transition', $from, $status, $reason, $actor->id);

            return $incident;
        });
    }
}
