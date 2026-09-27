<?php

use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;

it('serves the configured public booking for the public romawear domain', function (): void {
    $domain = TenantDomain::factory()->create([
        'hostname' => 'romawear.com.br',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $unit = $domain->tenant->units()->create([
        'slug' => 'roma-barber',
        'name' => 'Roma Barber',
        'status' => 'active',
        'online_booking_enabled' => true,
        'timezone' => 'America/Sao_Paulo',
    ]);

    $response = $this->withoutVite()->get('http://romawear.com.br/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('public-booking/show')
        ->where('unit.slug', $unit->slug));
});

it('keeps the public romawear domain on the tenant landing page until booking is configured', function (): void {
    TenantDomain::factory()->create([
        'hostname' => 'romawear.com.br',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);

    $response = $this->withoutVite()->get('http://romawear.com.br/');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/coming-soon'))
        ->assertDontSee('Exemplo de agendamento');
});

it('keeps the coming-soon page for other public domains', function (): void {
    $domain = TenantDomain::factory()->create([
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);

    $response = $this->withoutVite()->get('http://'.$domain->hostname.'/');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/coming-soon'))
        ->assertDontSee('Exemplo de agendamento');
});

it('does not serve the demo for the admin hostname even with public kind', function (): void {
    TenantDomain::factory()->create([
        'hostname' => 'admin.romawear.com.br',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);

    $response = $this->withoutVite()->get('http://admin.romawear.com.br/');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/coming-soon'))
        ->assertDontSee('Exemplo de agendamento');
});
