<?php

use App\Models\TelemetryIncident;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\Telemetry\IncidentCollector;
use App\Support\Telemetry\IncidentWorkflow;
use App\Support\Telemetry\RuntimeHealth;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config(['telemetry.enabled' => true, 'telemetry.release' => str_repeat('a', 40), 'telemetry.probe' => false]);
    Cache::flush();
});

it('captures only safe metadata and deduplicates without retaining exception messages', function (): void {
    $collector = app(IncidentCollector::class);
    $exception = new RuntimeException('customer@example.com password=secret card=4111111111111111');
    $first = $collector->captureThrowable($exception);
    $second = $collector->captureThrowable($exception);

    expect($first)->not->toBeNull()
        ->and($second->id)->toBe($first->id)
        ->and($second->occurrences)->toBe(2)
        ->and($second->exception_class)->toBe(RuntimeException::class)
        ->and(json_encode($second->toArray()))->not->toContain('customer@example.com', 'password', '4111111111111111', base_path());
    foreach ($second->frames as $frame) {
        expect(array_keys($frame))->not->toContain('args');
    }
});

it('retains repository frames without serializing authorization arguments', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $other = User::factory()->create(['email' => 'private-frame-argument@example.test']);
    $context = TenantContext::forUser($owner, $tenant->id, $unit->id);
    $caught = null;
    try {
        app(AuthorizationService::class)->assertTenantManager($other, $context, $tenant);
    } catch (AuthorizationException $exception) {
        $caught = $exception;
    }
    expect($caught)->not->toBeNull();
    $incident = app(IncidentCollector::class)->captureThrowable($caught);
    expect($incident->frames)->not->toBeEmpty()
        ->and($incident->frames[0]['file'])->toBe('app/Support/AuthorizationService.php')
        ->and(json_encode($incident->toArray()))->not->toContain($other->email, 'args', base_path());
});

it('does not collect disabled unknown-release or diagnostic probe events', function (): void {
    $collector = app(IncidentCollector::class);
    config(['telemetry.enabled' => false]);
    expect($collector->captureThrowable(new RuntimeException))->toBeNull();
    config(['telemetry.enabled' => true, 'telemetry.release' => 'unknown']);
    expect($collector->captureThrowable(new RuntimeException))->toBeNull();
    config(['telemetry.release' => str_repeat('a', 40), 'telemetry.probe' => true]);
    expect($collector->captureThrowable(new RuntimeException))->toBeNull();
    expect(TelemetryIncident::query()->count())->toBe(0);
});

it('requires repeated browser events and retains only existing built asset paths', function (): void {
    $asset = public_path('build/assets/telemetry-test-TestSafe1234.js');
    File::ensureDirectoryExists(dirname($asset));
    File::put($asset, '// fixture');
    try {
        $event = ['category' => 'javascript_error', 'code' => 'TypeError', 'path' => '/customers/private-customer-123', 'frames' => [
            ['file' => 'https://untrusted.invalid/build/assets/telemetry-test-TestSafe1234.js?secret=token', 'line' => 12, 'column' => 3],
            ['file' => '/uploads/private-customer-name.js', 'line' => 1],
        ]];
        $collector = app(IncidentCollector::class);
        $collector->captureBrowser($event);
        $collector->captureBrowser($event);
        expect($collector->claim())->toBeNull();
        $third = $collector->captureBrowser($event);
        expect($third->frames)->toBe([['file' => '/build/assets/telemetry-test-TestSafe1234.js', 'line' => 12, 'column' => 3]])
            ->and(json_encode($third->toArray()))->not->toContain('private-customer', 'untrusted.invalid', 'secret=token');
        $claim = $collector->claim();
        expect($claim['id'])->toBe($third->id)->and($claim['occurrences'])->toBe(3);
    } finally {
        File::delete($asset);
    }
});

it('leases once rejects stale acknowledgements and stops after two attempts', function (): void {
    $collector = app(IncidentCollector::class);
    $exception = new RuntimeException;
    $incident = $collector->captureThrowable($exception);
    $collector->captureThrowable($exception);
    $first = $collector->claim();
    expect($first['id'])->toBe($incident->id)
        ->and(Str::isUuid($first['claim_id']))->toBeTrue()
        ->and($collector->claim())->toBeNull()
        ->and($collector->finish($incident->id, (string) Str::uuid(), 'completed'))->toBeFalse();
    $this->travel(61)->minutes();
    $second = $collector->claim();
    expect($second['claim_id'])->not->toBe($first['claim_id'])
        ->and($collector->finish($incident->id, $first['claim_id'], 'completed'))->toBeFalse()
        ->and($collector->finish($incident->id, $second['claim_id'], 'failed'))->toBeTrue()
        ->and($collector->claim())->toBeNull();
});

it('keeps completed and blocked incidents terminal across days until the release changes', function (string $result): void {
    $collector = app(IncidentCollector::class);
    $exception = new RuntimeException;
    $incident = $collector->captureThrowable($exception);
    $collector->captureThrowable($exception);
    $claim = $collector->claim();
    expect($collector->finish($incident->id, $claim['claim_id'], $result))->toBeTrue();
    $collector->captureThrowable($exception);
    expect($collector->claim())->toBeNull()->and(TelemetryIncident::query()->count())->toBe(1);
    $this->travel(1)->days();
    $repeated = $collector->captureThrowable($exception);
    expect($repeated->id)->toBe($incident->id)
        ->and($repeated->status)->toBe($result === 'completed' ? 'completed' : 'failed')
        ->and($repeated->result)->toBe($result)
        ->and($repeated->attempts)->toBe(1)
        ->and($repeated->occurrences)->toBe(4)
        ->and($repeated->last_seen_at->toDateString())->toBe(now()->toDateString())
        ->and($collector->claim())->toBeNull();
    $this->travel(30)->days();
    expect($collector->captureThrowable($exception)->id)->toBe($incident->id)
        ->and($collector->prune())->toBe(0)
        ->and(TelemetryIncident::query()->count())->toBe(1);
    config(['telemetry.release' => str_repeat('b', 40)]);
    $collector->captureThrowable($exception);
    expect($collector->captureThrowable($exception)->id)->not->toBe($incident->id)
        ->and($collector->claim())->not->toBeNull();
})->with(['completed', 'blocked']);

it('rejects arbitrary payload fields and validates browser event categories', function (): void {
    $this->postJson(route('telemetry.browser'), ['category' => 'javascript_error', 'code' => 'TypeError', 'message' => 'private'])->assertUnprocessable()->assertJsonValidationErrors('payload');
    $this->postJson(route('telemetry.browser'), ['category' => 'arbitrary', 'code' => 'private customer name'])->assertUnprocessable();
    $this->postJson(route('telemetry.browser'), ['category' => 'inertia_http_exception', 'code' => '500', 'path' => '/customers/123?secret=token'])->assertUnprocessable();
    $this->postJson(route('telemetry.browser'), ['category' => 'network_error', 'code' => 'network_error'])->assertAccepted();
    expect(TelemetryIncident::query()->count())->toBe(1);
});

it('enforces a global browser rate cap across different source addresses', function (): void {
    config(['telemetry.global_per_minute' => 2]);
    $event = ['category' => 'javascript_error', 'code' => 'TypeError'];
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->postJson(route('telemetry.browser'), $event)->assertAccepted();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->postJson(route('telemetry.browser'), $event)->assertAccepted();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.3'])->postJson(route('telemetry.browser'), $event)->assertTooManyRequests();
    expect(TelemetryIncident::query()->sole()->occurrences)->toBe(2);
});

it('enforces web CSRF protection outside the testing bypass', function (): void {
    $this->app['env'] = 'production';
    $this->withSession(['_token' => 'test-csrf-token'])->postJson(route('telemetry.browser'), ['category' => 'network_error', 'code' => 'network_error'])->assertStatus(419);
    expect(TelemetryIncident::query()->count())->toBe(0);
});

it('bounds record growth and prunes old incidents while protecting live leases', function (): void {
    $collector = app(IncidentCollector::class);
    config(['telemetry.max_records' => 2]);
    $first = $collector->captureThrowable(new RuntimeException);
    $second = $collector->captureThrowable(new LogicException);
    expect($collector->captureThrowable(new UnexpectedValueException))->toBeNull();
    $first->forceFill(['last_seen_at' => now()->subDays(31)])->save();
    $second->forceFill(['status' => 'running', 'lease_expires_at' => now()->addHour(), 'last_seen_at' => now()->subDays(31)])->save();
    expect($collector->prune())->toBe(1)
        ->and(TelemetryIncident::query()->count())->toBe(1)
        ->and(TelemetryIncident::query()->sole()->id)->toBe($second->id);
});

it('fails safely when incident storage is unavailable', function (): void {
    config(['database.connections.telemetry_invalid' => ['driver' => 'missing'], 'database.default' => 'telemetry_invalid']);
    expect(app(IncidentCollector::class)->captureThrowable(new RuntimeException('must not escape')))->toBeNull();
    config(['database.default' => 'sqlite']);
});

it('exposes claim and finish through validated machine-readable console commands', function (): void {
    $collector = app(IncidentCollector::class);
    $this->artisan('telemetry:claim --json')->expectsOutput('null')->assertSuccessful();
    $exception = new RuntimeException;
    $incident = $collector->captureThrowable($exception);
    $collector->captureThrowable($exception);
    $claim = $collector->claim();
    $this->artisan('telemetry:finish', ['id' => $incident->id, '--claim' => $claim['claim_id'], '--status' => 'completed', '--json' => true])->assertSuccessful();
    $this->artisan('telemetry:finish', ['id' => $incident->id, '--claim' => $claim['claim_id'], '--status' => 'completed'])->assertFailed();
});

it('classifies repeated 404 from a dashboard as logging-only navigation without inferring an internal broken link', function (): void {
    $collector = app(IncidentCollector::class);
    $event = ['category' => 'inertia_http_exception', 'code' => '404', 'path' => '/dashboard'];
    for ($index = 0; $index < 5; $index++) {
        $incident = $collector->captureBrowser($event);
    }
    expect($incident->classification)->toBe('navigation')
        ->and($incident->severity)->toBe('low')
        ->and($incident->http_status)->toBe(404)
        ->and($incident->route_name)->toBe('dashboard')
        ->and($incident->auto_diagnosis_eligible)->toBeFalse()
        ->and($collector->claim())->toBeNull();
});

it('keeps expected HTTP responses in the incident center without auto diagnosis', function (int $status): void {
    $collector = app(IncidentCollector::class);
    $exception = new HttpException($status, 'private@example.test');
    for ($index = 0; $index < 4; $index++) {
        $incident = $collector->captureThrowable($exception);
    }
    expect($incident->classification)->toBe('expected_request')
        ->and($incident->http_status)->toBe($status)
        ->and($incident->auto_diagnosis_eligible)->toBeFalse()
        ->and($collector->claim())->toBeNull()
        ->and(json_encode($incident->toArray()))->not->toContain('private@example.test');
})->with([401, 403, 419, 422]);

it('prioritizes critical typed business failures over repeated generic backend incidents', function (): void {
    $collector = app(IncidentCollector::class);
    $exception = new RuntimeException;
    $generic = $collector->captureThrowable($exception);
    expect($generic->severity)->toBe('high')->and($collector->claim())->toBeNull();
    $collector->captureThrowable($exception);
    $critical = $collector->captureSignal('operational', 'appointment_creation_failed');
    expect($critical->severity)->toBe('critical')
        ->and($critical->module)->toBe('booking')
        ->and($critical->classification)->toBe('operation')
        ->and($collector->claim()['id'])->toBe($critical->id)
        ->and($collector->claim()['id'])->toBe($generic->id);
});

it('accepts only typed anomaly signals and requires explicit internal navigation evidence', function (): void {
    $collector = app(IncidentCollector::class);
    expect($collector->captureSignal('integrity', 'customer-private-name'))->toBeNull()
        ->and($collector->captureSignal('arbitrary', 'slow_route'))->toBeNull();
    $integrity = $collector->captureSignal('integrity', 'unbalanced_closing');
    $performance = $collector->captureSignal('performance', 'slow_query', 'private.customer.name');
    $navigation = $collector->captureSignal('internal_navigation', 'broken_internal_link');
    expect($integrity->classification)->toBe('data_integrity')
        ->and($performance->classification)->toBe('performance')
        ->and($performance->route_name)->toBeNull()
        ->and($navigation->classification)->toBe('internal_navigation')
        ->and(json_encode($performance->toArray()))->not->toContain('private.customer.name');
});

it('caps server signal collection without throwing into the application', function (): void {
    config(['telemetry.server_per_minute' => 2]);
    $collector = app(IncidentCollector::class);
    $exception = new RuntimeException;
    expect($collector->captureThrowable($exception))->not->toBeNull()
        ->and($collector->captureThrowable($exception))->not->toBeNull()
        ->and($collector->captureThrowable($exception))->toBeNull()
        ->and(TelemetryIncident::query()->sole()->occurrences)->toBe(2);
});

it('separates worker execution completion from incident resolution and audits administrator workflow', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $collector = app(IncidentCollector::class);
    $incident = $collector->captureSignal('operational', 'appointment_creation_failed');
    $claim = $collector->claim();
    $collector->finish($incident->id, $claim['claim_id'], 'completed');
    expect($incident->fresh()->workflow_status)->toBe('new');
    $workflow = app(IncidentWorkflow::class);
    $updated = $workflow->transition($incident->id, 'triaged', $admin, $admin->id, 0, 'triage_confirmed');
    expect($updated->owner_user_id)->toBe($admin->id)->and($updated->lock_version)->toBe(1);
    expect(fn () => $workflow->transition($incident->id, 'in_analysis', $admin, $admin->id, 0, 'investigation_started'))->toThrow(ConflictHttpException::class);
    $updated = $workflow->transition($incident->id, 'in_analysis', $admin, $admin->id, 1, 'investigation_started');
    $updated = $workflow->transition($incident->id, 'proposal_ready', $admin, $admin->id, 2, 'proposal_recorded');
    $updated = $workflow->transition($incident->id, 'in_validation', $admin, $admin->id, 3, 'validation_started');
    $updated = $workflow->transition($incident->id, 'resolved', $admin, $admin->id, 4, 'fix_verified');
    expect($updated->resolved_at)->not->toBeNull()
        ->and(DB::table('telemetry_incident_events')->where('incident_id', $incident->id)->where('event_type', 'workflow_transition')->count())->toBe(5);
    $updated = $workflow->transition($incident->id, 'triaged', $admin, null, 5, 'reopened');
    expect($updated->resolved_at)->toBeNull()
        ->and($updated->status)->toBe('completed')
        ->and($collector->claim())->toBeNull();
});

it('rejects unauthorized actors invalid owners and invalid workflow jumps', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $regular = User::factory()->create();
    $incident = app(IncidentCollector::class)->captureSignal('operational', 'appointment_creation_failed');
    $workflow = app(IncidentWorkflow::class);
    expect(fn () => $workflow->transition($incident->id, 'triaged', $regular, null, 0, 'triage_confirmed'))->toThrow(AuthorizationException::class)
        ->and(fn () => $workflow->transition($incident->id, 'resolved', $admin, null, 0, 'fix_verified'))->toThrow(ValidationException::class)
        ->and(fn () => $workflow->transition($incident->id, 'triaged', $admin, $regular->id, 0, 'triage_confirmed'))->toThrow(ValidationException::class);
});

it('stores typed runner health without credentials and marks explicit recovery', function (): void {
    $health = app(RuntimeHealth::class);
    $checks = array_map(fn (string $key): array => ['key' => $key, 'status' => 'ok'], ['image', 'network', 'auth_file', 'proxy', 'paused', 'freshness', 'queue', 'disk']);
    $degradedChecks = $checks;
    $degradedChecks[2]['status'] = 'missing';
    $payload = ['version' => 1, 'overall' => 'degraded', 'checks' => $degradedChecks];
    $snapshot = $health->report($payload);
    expect($snapshot['status'])->toBe('degraded')
        ->and(TelemetryIncident::query()->sole()->module)->toBe('telemetry')
        ->and(TelemetryIncident::query()->sole()->auto_diagnosis_eligible)->toBeFalse();
    $snapshot = $health->report(['version' => 1, 'overall' => 'healthy', 'checks' => $checks]);
    expect($snapshot['resolved_at'])->not->toBeNull()->and($snapshot['last_healthy_at'])->not->toBeNull();
    expect(fn () => $health->report([...$payload, 'token' => 'private']))->toThrow(ValidationException::class)
        ->and(fn () => $health->report(['version' => 1, 'overall' => 'degraded', 'checks' => [['key' => 'auth_file', 'status' => 'missing', 'code' => 'private-secret']]]))->toThrow(ValidationException::class);
});

it('rejects incomplete and inconsistent healthy runtime snapshots', function (): void {
    $health = app(RuntimeHealth::class);
    $checks = array_map(fn (string $key): array => ['key' => $key, 'status' => 'ok'], ['image', 'network', 'auth_file', 'proxy', 'paused', 'freshness', 'queue', 'disk']);
    expect(fn () => $health->report(['version' => 1, 'overall' => 'healthy', 'checks' => []]))->toThrow(ValidationException::class)
        ->and(fn () => $health->report(['version' => 1, 'overall' => 'healthy', 'checks' => array_slice($checks, 0, 7)]))->toThrow(ValidationException::class);
    $checks[2]['status'] = 'error';
    expect(fn () => $health->report(['version' => 1, 'overall' => 'healthy', 'checks' => $checks]))->toThrow(ValidationException::class)
        ->and(fn () => $health->report(['version' => 1, 'overall' => 'paused', 'checks' => $checks]))->toThrow(ValidationException::class);
    expect(DB::table('telemetry_runtime_health')->count())->toBe(0);
});

it('reports actionable queue metadata without claiming incidents', function (): void {
    $collector = app(IncidentCollector::class);
    $collector->captureBrowser(['category' => 'inertia_http_exception', 'code' => '404']);
    $incident = $collector->captureSignal('operational', 'appointment_creation_failed');
    $incident->forceFill(['first_seen_at' => now()->subMinutes(3)])->save();
    expect($collector->queueHealth())->toBe(['pending' => 1, 'running' => 0, 'oldest_pending_seconds' => 180]);
    $this->artisan('telemetry:queue-health --json')->assertSuccessful();
    expect($incident->fresh()->status)->toBe('pending');
});

it('captures actual HTTP response events and avoids duplicate incident records after an exception', function (): void {
    $request = Request::create('/customers/123?private=secret');
    $route = (new Route('GET', '/customers/{customer}', fn () => null))->name('customers.show');
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);
    event(new RequestHandled($request, new Response('', 404)));
    $notFound = TelemetryIncident::query()->sole();
    expect($notFound->classification)->toBe('navigation')->and($notFound->auto_diagnosis_eligible)->toBeFalse();
    app(IncidentCollector::class)->captureThrowable(new RuntimeException('private@example.test'));
    event(new RequestHandled($request, new Response('', 500)));
    expect(TelemetryIncident::query()->count())->toBe(2)
        ->and(json_encode(TelemetryIncident::query()->get()->toArray()))->not->toContain('private=secret', 'private@example.test', 'customers/123');
});

it('detects sampled slow routes and queries without retaining SQL bindings or request values', function (): void {
    config(['telemetry.performance_sample_rate' => 1]);
    $request = Request::create('/customers/private-customer');
    $route = (new Route('GET', '/customers/{customer}', fn () => null))->name('customers.show');
    $request->setRouteResolver(fn () => $route);
    $request->server->set('REQUEST_TIME_FLOAT', microtime(true) - 5);
    app()->instance('request', $request);
    event(new QueryExecuted('select * from telemetry_incidents where token = ?', ['secret-token'], 5000, DB::connection()));
    expect(TelemetryIncident::query()->count())->toBe(0);
    event(new QueryExecuted('select * from customers where email = ?', ['private@example.test'], 4000, DB::connection()));
    event(new RequestHandled($request, new Response('', 200)));
    expect(TelemetryIncident::query()->count())->toBe(2)
        ->and(TelemetryIncident::query()->where('duration_bucket', '3s_10s')->count())->toBe(2)
        ->and(json_encode(TelemetryIncident::query()->get()->toArray()))->not->toContain('select *', 'private@example.test', 'secret-token', 'private-customer');
});

it('skips diagnostic probes telemetry endpoints and unsampled performance events', function (): void {
    config(['telemetry.performance_sample_rate' => 0]);
    $request = Request::create('/customers/123');
    $request->server->set('REQUEST_TIME_FLOAT', microtime(true) - 10);
    app()->instance('request', $request);
    event(new QueryExecuted('select 1', [], 5000, DB::connection()));
    event(new RequestHandled($request, new Response('', 200)));
    config(['telemetry.performance_sample_rate' => 1]);
    $request->headers->set('X-Telemetry-Probe', '1');
    event(new RequestHandled($request, new Response('', 500)));
    $telemetry = Request::create('/telemetry/browser');
    app()->instance('request', $telemetry);
    event(new RequestHandled($telemetry, new Response('', 500)));
    expect(TelemetryIncident::query()->count())->toBe(0);
});
