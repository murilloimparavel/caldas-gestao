<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TelemetryIncidentIndexRequest;
use App\Http\Requests\Admin\TelemetryIncidentUpdateRequest;
use App\Models\TelemetryIncident;
use App\Models\User;
use App\Support\Telemetry\IncidentWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TelemetryIncidentController extends Controller
{
    public function index(TelemetryIncidentIndexRequest $request): Response|JsonResponse
    {
        $filters = array_filter($request->safe()->except('page'), fn (mixed $value): bool => $value !== null && $value !== '');
        $filters['period'] ??= '30';
        $query = TelemetryIncident::query()->where('last_seen_at', '>=', now()->subDays((int) $filters['period']));
        foreach (['classification', 'module', 'severity', 'workflow_status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $open = fn (Builder $query): Builder => $query->whereNotIn('workflow_status', ['resolved', 'ignored']);
        $metrics = [
            'total' => (clone $query)->count(),
            'open' => $open(clone $query)->count(),
            'critical' => $open(clone $query)->whereIn('severity', ['critical', 'high'])->count(),
            'resolved' => (clone $query)->where('workflow_status', 'resolved')->count(),
        ];
        $incidents = $query->orderByDesc('priority')->orderByDesc('last_seen_at')->paginate(20)->withQueryString();
        $owners = $this->owners();
        $incidents->through(fn (TelemetryIncident $incident): array => $this->incident($incident, $owners));
        $props = ['incidents' => $incidents, 'metrics' => $metrics, 'filters' => $filters, 'options' => $this->options(), 'executor' => $this->runtimeHealth()];

        return $request->expectsJson() ? response()->json($props) : Inertia::render('admin/incidents/index', $props);
    }

    public function show(Request $request, TelemetryIncident $incident): Response|JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $owners = $this->owners();
        $history = DB::table('telemetry_incident_events')->where('incident_id', $incident->getKey())
            ->orderByDesc('created_at')->limit(100)->get()->map(fn (object $event): array => [
                'id' => $event->id, 'event_type' => $event->event_type,
                'from_status' => $event->from_status, 'to_status' => $event->to_status,
                'reason' => $event->reason_code,
                'actor' => $event->actor_user_id === null ? 'Sistema' : ($owners[$event->actor_user_id]['name'] ?? 'Administrador'),
                'owner' => $owners[$event->owner_user_id]['name'] ?? null,
                'created_at' => $event->created_at,
            ])->all();
        $props = ['incident' => [...$this->incident($incident, $owners), 'frames' => $this->frames($incident), 'history' => $history],
            'owners' => array_values($owners), 'options' => $this->options()];

        return $request->expectsJson() ? response()->json($props) : Inertia::render('admin/incidents/show', $props);
    }

    public function update(TelemetryIncidentUpdateRequest $request, TelemetryIncident $incident, IncidentWorkflow $workflow): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        try {
            $updated = $workflow->transition($incident->getKey(), $data['workflow_status'], $request->user(), $data['owner_user_id'], (int) $data['lock_version'], $data['reason']);
        } catch (ConflictHttpException $exception) {
            if ($request->header('X-Inertia') === 'true') {
                return back()->with('error', 'O incidente mudou em outra sessão. Recarregue os dados antes de atualizar.');
            }
            throw $exception;
        }
        if ($request->expectsJson()) {
            return response()->json(['incident' => $this->incident($updated, $this->owners())]);
        }

        return to_route('admin.incidents.show', $incident)->with('success', 'Acompanhamento do incidente atualizado.');
    }

    /** @return array<string, array{id: string, name: string}> */
    private function owners(): array
    {
        $emails = array_values(array_filter(array_map(fn (mixed $email): string => strtolower(trim((string) $email)), (array) config('admin.super_admin_emails', []))));

        return User::query()->where(function (Builder $query) use ($emails): void {
            $query->where('is_super_admin', true);
            foreach ($emails as $email) {
                $query->orWhereRaw('LOWER(email) = ?', [$email]);
            }
        })->orderBy('name')->limit(100)->get(['id', 'name'])->mapWithKeys(fn (User $user): array => [$user->getKey() => ['id' => $user->getKey(), 'name' => $user->name]])->all();
    }

    /** @param array<string, array{id: string, name: string}> $owners
     * @return array<string, mixed>
     */
    private function incident(TelemetryIncident $incident, array $owners): array
    {
        return [...$incident->only(['id', 'title', 'classification', 'module', 'severity', 'confidence', 'impact_summary', 'workflow_status', 'owner_user_id', 'lock_version', 'detection_source', 'priority', 'auto_diagnosis_eligible', 'diagnosis_threshold', 'http_status', 'release', 'kind', 'category', 'exception_class', 'route_name', 'occurrences', 'status', 'result', 'attempts', 'first_seen_at', 'last_seen_at', 'resolved_at']),
            'owner' => $owners[$incident->owner_user_id] ?? null];
    }

    /** @return list<array<string, int|string>> */
    private function frames(TelemetryIncident $incident): array
    {
        $frames = $incident->getAttribute('frames');
        if (! is_array($frames)) {
            return [];
        }
        $safe = [];
        foreach (array_slice($frames, 0, 8) as $frame) {
            if (! is_array($frame)) {
                continue;
            }
            $file = $frame['file'] ?? null;
            if (! is_string($file) || ! preg_match('#^(?:/?build/assets/|app/|bootstrap/|routes/|resources/|config/)[A-Za-z0-9_./-]+$#', $file) || str_contains($file, '..')) {
                continue;
            }
            $entry = ['file' => $file, 'line' => is_numeric($frame['line'] ?? null) ? max(1, (int) $frame['line']) : 1];
            if (is_numeric($frame['column'] ?? null)) {
                $entry['column'] = max(0, (int) $frame['column']);
            }
            $safe[] = $entry;
        }

        return $safe;
    }

    /** @return array<string, mixed> */
    private function runtimeHealth(): array
    {
        $row = DB::table('telemetry_runtime_health')->where('component', 'runner')->first();
        $labels = ['image' => 'Imagem do executor', 'network' => 'Rede isolada', 'auth_file' => 'Arquivo de autenticação', 'proxy' => 'Saída controlada', 'paused' => 'Pausa operacional', 'freshness' => 'Atualização do monitor', 'queue' => 'Fila de diagnóstico', 'disk' => 'Espaço em disco'];
        $reported = $row === null ? [] : json_decode($row->checks, true);
        $byKey = [];
        if (is_array($reported)) {
            foreach ($reported as $check) {
                if (is_array($check) && is_string($check['key'] ?? null)) {
                    $byKey[$check['key']] = in_array($check['status'] ?? null, ['ok', 'missing', 'error', 'paused', 'stale', 'unknown'], true) ? $check['status'] : 'unknown';
                }
            }
        }
        $checks = [];
        foreach ($labels as $key => $label) {
            $checks[] = ['key' => $key, 'label' => $label, 'status' => $byKey[$key] ?? 'unknown'];
        }
        $lastSeen = $row?->last_seen_at === null ? null : CarbonImmutable::parse($row->last_seen_at);
        $stale = $lastSeen === null || $lastSeen->lt(now()->subMinutes(5));
        $status = in_array($row?->status, ['healthy', 'degraded', 'paused'], true) ? $row->status : 'unknown';
        if ($row !== null && $stale) {
            $status = 'stale';
        } elseif ($status === 'healthy' && collect($checks)->contains(fn (array $check): bool => $check['status'] !== 'ok')) {
            $status = 'degraded';
        }

        return ['status' => $status, 'checks' => $checks, 'stale' => $stale, 'last_seen_at' => $lastSeen?->toIso8601String(), 'last_healthy_at' => $row?->last_healthy_at === null ? null : CarbonImmutable::parse($row->last_healthy_at)->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'workflow_status' => (array) config('telemetry.workflow_statuses', []),
            'severity' => (array) config('telemetry.severities', []),
            'classification' => (array) config('telemetry.classifications', []),
            'module' => (array) config('telemetry.modules', []),
            'transitions' => (array) config('telemetry.workflow_transitions', []),
            'reason' => (array) config('telemetry.workflow_reasons', []),
        ];
    }
}
