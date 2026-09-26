<?php

use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;

it('serves the romawear booking demo for the public romawear domain', function (): void {
    TenantDomain::factory()->create([
        'hostname' => 'romawear.com.br',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);

    $response = $this->get('http://romawear.com.br/');

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    ob_start();
    $response->sendContent();
    $streamedContent = ob_get_clean();

    expect($streamedContent)
        ->toContain('Exemplo de agendamento')
        ->toContain('Bruno Roma');
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
