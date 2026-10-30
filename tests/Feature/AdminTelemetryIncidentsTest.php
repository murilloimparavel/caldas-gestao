<?php

use App\Models\TelemetryIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function adminTelemetryIncident(array $attributes = []): TelemetryIncident
{
    return TelemetryIncident::query()->create([
        'fingerprint' => hash('sha256', (string) Str::uuid()), 'release' => str_repeat('a', 40),
        'kind' => 'server', 'category' => 'server_exception', 'exception_class' => 'TypeError',
        'frames' => [['file' => 'app/Support/Example.php', 'line' => 12]],
        'occurrences' => 3, 'status' => 'completed', 'result' => 'completed', 'attempts' => 1,
        'first_seen_at' => now()->subHour(), 'last_seen_at' => now(),
        'title' => 'Falha no agendamento', 'classification' => 'backend', 'module' => 'booking',
        'severity' => 'high', 'confidence' => 90, 'impact_summary' => 'Um fluxo pode estar indisponível.',
        'workflow_status' => 'new', 'lock_version' => 0,
        'detection_source' => 'server', 'priority' => 80,
        ...$attributes,
    ]);
}

it('restricts every central incident endpoint to super administrators', function (): void {
    $incident = adminTelemetryIncident();
    $this->get(route('admin.incidents.index'))->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->create());
    $this->get(route('admin.incidents.index'))->assertForbidden();
    $this->get(route('admin.incidents.show', $incident))->assertForbidden();
    $this->patchJson(route('admin.incidents.update', $incident), [
        'workflow_status' => 'triaged', 'owner_user_id' => null,
        'lock_version' => 0, 'reason' => 'triage_confirmed',
    ])->assertForbidden();
    expect($incident->fresh()->workflow_status)->toBe('new');
});

it('shows a sanitized cross-platform catalog with matching filters and metrics', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $matching = adminTelemetryIncident();
    adminTelemetryIncident(['module' => 'finance', 'severity' => 'low', 'workflow_status' => 'resolved']);
    adminTelemetryIncident(['last_seen_at' => now()->subDays(10)]);

    $this->actingAs($admin)->getJson(route('admin.incidents.index', [
        'module' => 'booking', 'classification' => 'backend', 'severity' => 'high', 'workflow_status' => 'new', 'period' => '7',
    ]))->assertSuccessful()
        ->assertJsonPath('metrics.total', 1)->assertJsonPath('metrics.open', 1)
        ->assertJsonPath('metrics.critical', 1)->assertJsonPath('metrics.resolved', 0)
        ->assertJsonPath('incidents.data.0.id', $matching->getKey())
        ->assertJsonPath('incidents.data.0.workflow_status', 'new')
        ->assertJsonPath('incidents.data.0.status', 'completed')
        ->assertJsonMissingPath('incidents.data.0.claim_id')
        ->assertJsonMissingPath('incidents.data.0.frames')
        ->assertJsonMissingPath('incidents.data.0.fingerprint');
});

it('limits the detail to safe frames and eligible owner names without user emails', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    User::factory()->create(['name' => 'Cliente privado', 'email' => 'customer-private@example.test']);
    $incident = adminTelemetryIncident(['frames' => [
        ['file' => 'app/Support/Example.php', 'line' => 12, 'message' => 'customer-private@example.test'],
        ['file' => 'https://example.test/private?token=secret', 'line' => 99],
    ]]);

    $response = $this->actingAs($admin)->getJson(route('admin.incidents.show', $incident))
        ->assertSuccessful()->assertJsonCount(1, 'incident.frames')->assertJsonCount(1, 'owners')
        ->assertJsonPath('incident.frames.0.file', 'app/Support/Example.php')
        ->assertJsonPath('owners.0.id', $admin->getKey())
        ->assertJsonMissingPath('owners.0.email')
        ->assertJsonMissingPath('incident.frames.0.message');
    expect($response->getContent())->not->toContain('customer-private@example.test', 'token=secret');
});

it('audits triage and owner assignment without marking a completed execution as resolved', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $incident = adminTelemetryIncident();
    $this->actingAs($admin)->patchJson(route('admin.incidents.update', $incident), [
        'workflow_status' => 'triaged', 'owner_user_id' => $admin->getKey(),
        'lock_version' => '0', 'reason' => 'triage_confirmed',
    ])->assertSuccessful()->assertJsonPath('incident.workflow_status', 'triaged')
        ->assertJsonPath('incident.status', 'completed');
    expect($incident->fresh()->resolved_at)->toBeNull()
        ->and($incident->fresh()->owner_user_id)->toBe($admin->getKey())
        ->and($incident->fresh()->lock_version)->toBe(1);
    $event = DB::table('telemetry_incident_events')->where('incident_id', $incident->getKey())->latest('created_at')->first();
    expect($event->actor_user_id)->toBe($admin->getKey())->and($event->reason_code)->toBe('triage_confirmed');
});

it('rejects stale updates and disallowed owner or workflow inputs without recording changes', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $customer = User::factory()->create();
    $incident = adminTelemetryIncident(['lock_version' => 2]);
    $payload = ['workflow_status' => 'triaged', 'owner_user_id' => null, 'lock_version' => 0, 'reason' => 'triage_confirmed'];
    $this->actingAs($admin)->patchJson(route('admin.incidents.update', $incident), $payload)->assertStatus(409);
    $this->patchJson(route('admin.incidents.update', $incident), [...$payload, 'lock_version' => 2, 'owner_user_id' => $customer->getKey()])->assertUnprocessable();
    $this->patchJson(route('admin.incidents.update', $incident), [...$payload, 'lock_version' => 2, 'workflow_status' => 'resolved', 'reason' => 'fix_verified'])->assertUnprocessable();
    $this->patchJson(route('admin.incidents.update', $incident), [...$payload, 'lock_version' => 2, 'reason' => 'customer-private@example.test'])->assertUnprocessable();
    expect($incident->fresh()->lock_version)->toBe(2)
        ->and(DB::table('telemetry_incident_events')->where('incident_id', $incident->getKey())->count())->toBe(0);
});

it('records resolution only after the explicit validation transition and supports reopening', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $incident = adminTelemetryIncident(['workflow_status' => 'in_validation']);
    $this->actingAs($admin)->patchJson(route('admin.incidents.update', $incident), [
        'workflow_status' => 'resolved', 'owner_user_id' => null, 'lock_version' => 0, 'reason' => 'fix_verified',
    ])->assertSuccessful();
    expect($incident->fresh()->resolved_at)->not->toBeNull();
    $this->patchJson(route('admin.incidents.update', $incident), [
        'workflow_status' => 'triaged', 'owner_user_id' => $admin->getKey(), 'lock_version' => 1, 'reason' => 'reopened',
    ])->assertSuccessful();
    expect($incident->fresh()->resolved_at)->toBeNull()->and($incident->fresh()->workflow_status)->toBe('triaged');
});

it('renders the central pages and records a same-state owner change with its revision', function (): void {
    $this->withoutVite();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $incident = adminTelemetryIncident();
    $this->actingAs($admin)->get(route('admin.incidents.index'))->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/incidents/index'));
    $this->get(route('admin.incidents.show', $incident))->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/incidents/show'));
    $this->patchJson(route('admin.incidents.update', $incident), [
        'workflow_status' => 'new', 'owner_user_id' => $admin->getKey(), 'lock_version' => 0, 'reason' => 'owner_assigned',
    ])->assertSuccessful();
    expect($incident->fresh()->lock_version)->toBe(1)->and($incident->fresh()->workflow_status)->toBe('new');
    $this->getJson(route('admin.incidents.show', $incident))->assertSuccessful()
        ->assertJsonPath('incident.history.0.reason', 'owner_assigned');
});

it('distinguishes an unmonitored executor from a stale or incomplete healthy snapshot', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin)->getJson(route('admin.incidents.index'))->assertSuccessful()
        ->assertJsonPath('executor.status', 'unknown')->assertJsonPath('executor.last_seen_at', null)
        ->assertJsonCount(8, 'executor.checks')->assertJsonPath('executor.checks.0.status', 'unknown');
    DB::table('telemetry_runtime_health')->insert([
        'component' => 'runner', 'status' => 'healthy', 'checks' => json_encode([['key' => 'image', 'status' => 'ok']]),
        'first_seen_at' => now()->subHour(), 'last_seen_at' => now(), 'last_healthy_at' => now(),
    ]);
    $this->getJson(route('admin.incidents.index'))->assertSuccessful()
        ->assertJsonPath('executor.status', 'degraded')->assertJsonPath('executor.stale', false);
    DB::table('telemetry_runtime_health')->where('component', 'runner')->update(['last_seen_at' => now()->subMinutes(6)]);
    $this->getJson(route('admin.incidents.index'))->assertSuccessful()
        ->assertJsonPath('executor.status', 'stale')->assertJsonPath('executor.stale', true);
});
