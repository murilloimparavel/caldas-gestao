<?php

use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\OnlineBookingSite;
use App\Support\TenantContext;

it('creates a tenant-scoped campaign link with normalized UTM parameters', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(SaveOnlineBookingDraft::class)->handle($owner, $context, ['service_ids' => [], 'professional_ids' => []], 0);

    $response = $this->actingAs($owner)->postJson(route('online_booking.campaign_links.store'), [
        'name' => 'Instagram campanha setembro',
        'utm_source' => 'instagram',
        'utm_medium' => 'social',
        'utm_campaign' => 'setembro_barbearia',
        'utm_content' => 'bio',
    ]);

    $response->assertCreated()->assertJsonPath('campaign_link.utm_source', 'instagram')->assertJsonPath('campaign_link.utm_campaign', 'setembro_barbearia');
    expect($response->json('campaign_link.url'))->toContain('utm_source=instagram')->toContain('utm_medium=social');
});

it('toggles and deletes only links from the active unit', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(SaveOnlineBookingDraft::class)->handle($owner, $context, ['service_ids' => [], 'professional_ids' => []], 0);
    $created = $this->actingAs($owner)->postJson(route('online_booking.campaign_links.store'), [
        'name' => 'WhatsApp', 'utm_source' => 'whatsapp', 'utm_medium' => 'direct', 'utm_campaign' => 'bio',
    ])->assertCreated()->json('campaign_link');

    $this->actingAs($owner)->patchJson(route('online_booking.campaign_links.toggle', $created['id']))
        ->assertOk()->assertJsonPath('status', 'inactive');
    $this->actingAs($owner)->deleteJson(route('online_booking.campaign_links.destroy', $created['id']))
        ->assertOk()->assertJsonPath('deleted', true);
});

it('generates campaign links on the active custom public domain', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(SaveOnlineBookingDraft::class)->handle($owner, $context, ['service_ids' => [], 'professional_ids' => []], 0);
    $domain = $tenant->domains()->create([
        'hostname' => 'agenda.example.com',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
        'verification_token' => 'test-token',
        'expected_cname' => 'vps.example.com',
    ]);
    OnlineBookingSite::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->update(['public_domain_id' => $domain->getKey()]);

    $response = $this->actingAs($owner)->postJson(route('online_booking.campaign_links.store'), [
        'name' => 'Site oficial', 'utm_source' => 'site', 'utm_medium' => 'owned', 'utm_campaign' => 'home',
    ]);

    $response->assertCreated();
    expect($response->json('campaign_link.url'))->toStartWith('https://agenda.example.com/?');
});
