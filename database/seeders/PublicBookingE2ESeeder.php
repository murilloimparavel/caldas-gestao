<?php

namespace Database\Seeders;

use App\Actions\Identity\OnboardTenant;
use App\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PublicBookingE2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Public booking E2E fixtures may only be seeded in the testing environment.');
        }

        DB::transaction(function (): void {
            $owner = User::factory()->create([
                'name' => 'Playwright E2E',
                'email' => 'e2e-booking@caldas.local',
                'password' => 'CaldasE2E!2026',
            ]);
            $atelierTenant = app(OnboardTenant::class)->handle($owner, [
                'name' => 'RomaWear E2E Atelier',
                'slug' => 'e2e-atelier',
                'timezone' => 'America/Sao_Paulo',
                'default_currency' => 'BRL',
            ], [
                'name' => 'Matriz',
                'slug' => 'matriz',
                'timezone' => 'America/Sao_Paulo',
            ]);
            $atelierUnit = $atelierTenant->units()->firstOrFail();
            $atelierUnit->update(['online_booking_enabled' => true]);

            $this->seedCatalog($atelierTenant, $atelierUnit, 'atelier-barber', 'e2e-atelier', $owner);

            $essentialTenant = Tenant::factory()->create([
                'name' => 'RomaWear E2E Essential',
                'slug' => 'e2e-essential',
                'timezone' => 'America/Sao_Paulo',
            ]);
            $essentialUnit = Unit::factory()->create([
                'tenant_id' => $essentialTenant->getKey(),
                'name' => 'Matriz',
                'slug' => 'matriz',
                'timezone' => 'America/Sao_Paulo',
                'online_booking_enabled' => true,
            ]);

            $this->seedCatalog($essentialTenant, $essentialUnit, 'essential', 'e2e-essential');
        });
    }

    private function seedCatalog(Tenant $tenant, Unit $unit, string $templateKey, string $publicSlug, ?User $updatedBy = null): void
    {
        $hairCategory = Category::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'name' => 'Cabelo',
        ]);
        $beardCategory = Category::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'name' => 'Barba',
        ]);
        $careCategory = Category::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'name' => 'Cuidados',
        ]);
        $haircut = $this->createService($tenant, $unit, $hairCategory, 'Corte clássico', 45, 5000);
        $beard = $this->createService($tenant, $unit, $beardCategory, 'Barba clássica', 30, 3500);
        $color = $this->createService($tenant, $unit, $careCategory, 'Coloração discreta', 30, 4000);
        $joao = $this->createProfessional($tenant, $unit, 'João Costa');
        $lucas = $this->createProfessional($tenant, $unit, 'Lucas Prado');

        $joao->services()->attach([$haircut->getKey(), $beard->getKey()], [
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
        ]);
        $lucas->services()->attach([$haircut->getKey(), $beard->getKey(), $color->getKey()], [
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
        ]);

        foreach (range(0, 6) as $weekday) {
            $this->createAvailabilityRule($tenant, $unit, $joao, $weekday, '10:00:00');
            $this->createAvailabilityRule($tenant, $unit, $lucas, $weekday, '09:00:00');
        }

        $hours = [];
        foreach (range(0, 6) as $weekday) {
            $hours[(string) $weekday] = ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00'];
        }

        OnlineBookingSetting::query()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'public_slug' => $publicSlug,
            'minimum_notice_minutes' => 0,
            'public_hours' => $hours,
        ]);
        $site = OnlineBookingSite::query()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'public_slug' => $publicSlug,
            'template_key' => $templateKey,
            'status' => 'published',
        ]);

        if ($updatedBy !== null) {
            $draftContent = [
                'schema_version' => 1,
                'appearance' => [],
                'identity' => [],
                'theme' => [],
                'sections' => [],
                'service_ids' => [$haircut->getKey(), $beard->getKey(), $color->getKey()],
                'professional_ids' => [$joao->getKey(), $lucas->getKey()],
                'public_hours' => $hours,
                'booking_policy' => ['minimum_notice_minutes' => 0, 'booking_flow' => 'service_first'],
                'gallery' => [],
                'seo' => [],
            ];

            OnlineBookingDraft::query()->create([
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'site_id' => $site->getKey(),
                'revision' => 1,
                'content' => $draftContent,
                'content_hash' => hash('sha256', json_encode($draftContent, JSON_THROW_ON_ERROR)),
                'updated_by' => $updatedBy->getKey(),
            ]);
        }
    }

    private function createService(Tenant $tenant, Unit $unit, Category $category, string $name, int $duration, int $priceCents): Service
    {
        return Service::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'category_id' => $category->getKey(),
            'name' => $name,
            'duration_minutes' => $duration,
            'price_cents' => $priceCents,
            'online_booking_enabled' => true,
        ]);
    }

    private function createProfessional(Tenant $tenant, Unit $unit, string $name): Professional
    {
        return Professional::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'name' => $name,
            'online_booking_enabled' => true,
        ]);
    }

    private function createAvailabilityRule(Tenant $tenant, Unit $unit, Professional $professional, int $weekday, string $start): void
    {
        AvailabilityRule::query()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'professional_id' => $professional->getKey(),
            'weekday' => $weekday,
            'starts_at' => $start,
            'ends_at' => '18:00:00',
            'timezone' => 'America/Sao_Paulo',
        ]);
    }
}
