<?php

use App\Models\TelemetryIncident;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\Telemetry\IncidentCollector;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
    $incident = $collector->captureThrowable(new RuntimeException);
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
        ->and($repeated->occurrences)->toBe(3)
        ->and($repeated->last_seen_at->toDateString())->toBe(now()->toDateString())
        ->and($collector->claim())->toBeNull();
    $this->travel(30)->days();
    expect($collector->captureThrowable($exception)->id)->toBe($incident->id)
        ->and($collector->prune())->toBe(0)
        ->and(TelemetryIncident::query()->count())->toBe(1);
    config(['telemetry.release' => str_repeat('b', 40)]);
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
    $incident = $collector->captureThrowable(new RuntimeException);
    $claim = $collector->claim();
    $this->artisan('telemetry:finish', ['id' => $incident->id, '--claim' => $claim['claim_id'], '--status' => 'completed', '--json' => true])->assertSuccessful();
    $this->artisan('telemetry:finish', ['id' => $incident->id, '--claim' => $claim['claim_id'], '--status' => 'completed'])->assertFailed();
});
