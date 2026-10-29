<?php

namespace App\Support\Telemetry;

use App\Models\TelemetryIncident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class IncidentCollector
{
    private bool $capturing = false;

    public function captureThrowable(Throwable $exception): ?TelemetryIncident
    {
        $frames = [];
        $trace = [['file' => $exception->getFile(), 'line' => $exception->getLine()], ...$exception->getTrace()];
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if (! str_starts_with($file, base_path().DIRECTORY_SEPARATOR)) {
                continue;
            }
            $relative = substr($file, strlen(base_path()) + 1);
            if (! preg_match('#^(?:app|bootstrap|routes|resources|config)/[A-Za-z0-9_./-]+\.php$#', $relative) || str_contains($relative, '..')) {
                continue;
            }
            $safe = ['file' => $relative, 'line' => max(1, (int) ($frame['line'] ?? 1))];
            foreach (['class', 'function'] as $key) {
                if (isset($frame[$key]) && preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,179}$/', $frame[$key])) {
                    $safe[$key] = $frame[$key];
                }
            }
            $frames[] = $safe;
            if (count($frames) >= 8) {
                break;
            }
        }
        $class = $exception::class;
        if (! preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,179}$/', $class)) {
            $class = 'Throwable';
        }

        return $this->record('server', 'server_exception', $class, $frames, $this->routeName());
    }

    /** @param array<string, mixed> $event */
    public function captureBrowser(array $event): ?TelemetryIncident
    {
        $frames = [];
        foreach (array_slice($event['frames'] ?? [], 0, 8) as $frame) {
            $file = parse_url($frame['file'], PHP_URL_PATH);
            if (! is_string($file) || ! preg_match('#^/build/assets/[A-Za-z0-9_-]+-[A-Za-z0-9_-]{8,}\.js$#', $file) || ! is_file(public_path(ltrim($file, '/')))) {
                continue;
            }
            $safe = ['file' => $file, 'line' => (int) $frame['line']];
            if (isset($frame['column'])) {
                $safe['column'] = (int) $frame['column'];
            }
            $frames[] = $safe;
        }
        $route = null;
        if (isset($event['path']) && is_string($event['path'])) {
            try {
                $route = app('router')->getRoutes()->match(Request::create($event['path']))->getName();
            } catch (Throwable) {
                $route = null;
            }
        }

        return $this->record('browser', $event['category'], $event['code'], $frames, $this->safeRouteName($route));
    }

    /** @param list<array<string, int|string>> $frames */
    private function record(string $kind, string $category, string $class, array $frames, ?string $route): ?TelemetryIncident
    {
        $release = (string) config('telemetry.release', 'unknown');
        if ($this->capturing || ! config('telemetry.enabled') || config('telemetry.probe') || request()->header('X-Telemetry-Probe') === '1' || ! preg_match('/^[a-f0-9]{40}$/', $release)) {
            return null;
        }
        $this->capturing = true;
        try {
            return DB::transaction(function () use ($kind, $category, $class, $frames, $route, $release): ?TelemetryIncident {
                $this->lock();
                $fingerprint = hash('sha256', json_encode([now('UTC')->toDateString(), $release, $kind, $category, $class, $frames, $route], JSON_THROW_ON_ERROR));
                $incident = TelemetryIncident::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();
                if ($incident !== null) {
                    $incident->forceFill(['occurrences' => min(1000000, $incident->occurrences + 1), 'last_seen_at' => now()])->save();

                    return $incident;
                }
                if (TelemetryIncident::query()->count() >= (int) config('telemetry.max_records', 1000)) {
                    return null;
                }

                return TelemetryIncident::query()->create(['fingerprint' => $fingerprint, 'release' => $release, 'kind' => $kind, 'category' => $category, 'exception_class' => $class, 'route_name' => $route, 'frames' => $frames, 'occurrences' => 1, 'status' => 'pending', 'attempts' => 0, 'first_seen_at' => now(), 'last_seen_at' => now()]);
            });
        } catch (Throwable) {
            return null;
        } finally {
            $this->capturing = false;
        }
    }

    /** @return array<string, mixed>|null */
    public function claim(): ?array
    {
        if (! config('telemetry.enabled')) {
            return null;
        }

        return DB::transaction(function (): ?array {
            $this->lock();
            $incident = TelemetryIncident::query()
                ->where('attempts', '<', (int) config('telemetry.max_attempts', 2))
                ->where(fn (Builder $query) => $query->whereNull('result')->orWhere('result', 'failed'))
                ->where(fn (Builder $query) => $query->whereIn('status', ['pending', 'failed'])->orWhere(fn (Builder $query) => $query->where('status', 'running')->where('lease_expires_at', '<=', now())))
                ->where(fn (Builder $query) => $query->where('kind', 'server')->orWhere('occurrences', '>=', max(3, (int) config('telemetry.browser_threshold', 3))))
                ->orderBy('first_seen_at')->lockForUpdate()->first();
            if ($incident === null) {
                return null;
            }
            $incident->forceFill(['status' => 'running', 'result' => null, 'claim_id' => (string) Str::uuid(), 'attempts' => $incident->attempts + 1, 'lease_expires_at' => now()->addMinutes((int) config('telemetry.lease_minutes', 60))])->save();

            return $incident->only(['id', 'claim_id', 'kind', 'category', 'release', 'exception_class', 'route_name', 'frames', 'occurrences']);
        });
    }

    public function finish(string $id, string $claim, string $result): bool
    {
        if (! Str::isUuid($id) || ! Str::isUuid($claim) || ! in_array($result, ['completed', 'failed', 'blocked'], true)) {
            return false;
        }

        return DB::transaction(function () use ($id, $claim, $result): bool {
            $this->lock();
            $incident = TelemetryIncident::query()->whereKey($id)->lockForUpdate()->first();
            if ($incident === null || $incident->status !== 'running' || ! hash_equals((string) $incident->claim_id, $claim) || $incident->lease_expires_at === null || $incident->lease_expires_at->isPast()) {
                return false;
            }
            $incident->forceFill(['status' => $result === 'completed' ? 'completed' : 'failed', 'result' => $result, 'lease_expires_at' => null])->save();

            return true;
        });
    }

    public function prune(): int
    {
        return DB::transaction(function (): int {
            $this->lock();
            $eligible = fn (): Builder => TelemetryIncident::query()->where(fn (Builder $query) => $query->where('status', '!=', 'running')->orWhere('lease_expires_at', '<=', now()));
            $deleted = $eligible()->where('last_seen_at', '<', now()->subDays((int) config('telemetry.retention_days', 30)))->delete();
            $excess = TelemetryIncident::query()->count() - (int) config('telemetry.max_records', 1000);
            if ($excess > 0) {
                $ids = $eligible()->orderBy('last_seen_at')->limit($excess)->pluck('id');
                $deleted += TelemetryIncident::query()->whereIn('id', $ids)->delete();
            }

            return $deleted;
        });
    }

    private function lock(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [88081223]);
        }
    }

    private function routeName(): ?string
    {
        try {
            return $this->safeRouteName(request()->route()?->getName());
        } catch (Throwable) {
            return null;
        }
    }

    private function safeRouteName(?string $name): ?string
    {
        return $name !== null && preg_match('/^[A-Za-z0-9_.-]{1,160}$/', $name) ? $name : null;
    }
}
