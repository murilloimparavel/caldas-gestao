<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Support\OnlineBookingReadiness;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

/**
 * Read-only, tenant and unit scoped integration queries.
 *
 * The returned arrays are an explicit disclosure boundary. They intentionally
 * do not serialize customer, contact, note, sale, email, phone, or image data.
 */
final class IntegrationCatalogQuery
{
    public function __construct(private readonly OnlineBookingReadiness $readiness) {}

    /**
     * @return Paginator<int, array<string, mixed>>
     */
    public function categories(TenantContext $context, int $perPage = 50, ?string $search = null, int $page = 1): Paginator
    {
        $this->unit($context);

        $query = Category::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->select(['name'])
            ->orderBy('name')
            ->orderBy('id');

        $this->applySearch($query, $search);

        return $query->simplePaginate($perPage, ['*'], 'page', $page)->through($this->mapCategory(...));
    }

    /** @return array{name: string}|null */
    public function category(TenantContext $context, string $categoryId): ?array
    {
        $this->unit($context);

        $category = Category::query()
            ->whereKey($categoryId)
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->first(['name']);

        return $category instanceof Category ? $this->categoryRecord($category) : null;
    }

    /**
     * @return Paginator<int, array<string, mixed>>
     */
    public function services(TenantContext $context, int $perPage = 50, ?string $search = null, int $page = 1): Paginator
    {
        $this->unit($context);

        $query = $this->serviceQuery($context)
            ->orderBy('name')
            ->orderBy('id');

        $this->applySearch($query, $search);

        return $query->simplePaginate($perPage, ['*'], 'page', $page)->through($this->mapService(...));
    }

    /** @return array{name: string, duration_minutes: int, price_cents: int}|null */
    public function service(TenantContext $context, string $serviceId): ?array
    {
        $this->unit($context);

        $service = $this->serviceQuery($context)
            ->whereKey($serviceId)
            ->first();

        return $service instanceof Service ? $this->serviceRecord($service) : null;
    }

    /**
     * @return Paginator<int, array<string, mixed>>
     */
    public function professionals(TenantContext $context, int $perPage = 50, ?string $search = null, int $page = 1): Paginator
    {
        $this->unit($context);

        $query = Professional::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->select(['name'])
            ->orderBy('name')
            ->orderBy('id');

        $this->applySearch($query, $search);

        return $query->simplePaginate($perPage, ['*'], 'page', $page)->through($this->mapProfessional(...));
    }

    /** @return array{name: string}|null */
    public function professional(TenantContext $context, string $professionalId): ?array
    {
        $this->unit($context);

        $professional = Professional::query()
            ->whereKey($professionalId)
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->first(['name']);

        return $professional instanceof Professional ? $this->professionalRecord($professional) : null;
    }

    /**
     * Return only setup indicators. This method deliberately never calls the
     * web booking Ensure action, which can create a site or draft.
     *
     * @return array{
     *     unit: array{active: bool, timezone_configured: bool, online_booking_enabled: bool},
     *     catalog: array{active_services: int, active_professionals: int, active_service_professional_pair: bool},
     *     availability: array{active_rules: int},
     *     booking: array{site_exists: bool, draft_exists: bool, published: bool, publishable: bool, blockers: list<string>}
     * }
     */
    public function setupStatus(TenantContext $context): array
    {
        $unit = $this->unit($context);
        $tenantId = (string) $context->tenant->getKey();
        $unitId = (string) $unit->getKey();

        $activeServices = Service::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->count();
        $activeProfessionals = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->count();
        $activePair = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereHas('services', fn (Builder $query): Builder => $query
                ->where('services.tenant_id', $tenantId)
                ->where('services.unit_id', $unitId)
                ->where('services.status', 'active'))
            ->exists();
        $activeRules = AvailabilityRule::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->count();
        $site = OnlineBookingSite::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->with([
                'draft:id,site_id,revision,content',
                'activePublication:id,site_id,published_at',
            ])
            ->first();
        $readiness = $this->readiness->check($context, $site, $site?->draft?->content);

        return [
            'unit' => [
                'active' => $unit->status->value === 'active',
                'timezone_configured' => filled($unit->timezone),
                'online_booking_enabled' => $unit->online_booking_enabled,
            ],
            'catalog' => [
                'active_services' => $activeServices,
                'active_professionals' => $activeProfessionals,
                'active_service_professional_pair' => $activePair,
            ],
            'availability' => ['active_rules' => $activeRules],
            'booking' => [
                'site_exists' => $site instanceof OnlineBookingSite,
                'draft_exists' => $site?->draft !== null,
                'published' => $site?->activePublication !== null,
                'publishable' => $readiness['publishable'],
                'blockers' => $readiness['blockers'],
            ],
        ];
    }

    /** @param Builder<Category>|Builder<Service>|Builder<Professional> $query */
    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }

        $query->where('name', 'like', '%'.trim($search).'%');
    }

    /** @return Builder<Service> */
    private function serviceQuery(TenantContext $context): Builder
    {
        return Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->select(['name', 'duration_minutes', 'price_cents']);
    }

    /** @return array<string, mixed> */
    private function mapCategory(Category $category): array
    {
        return $this->categoryRecord($category);
    }

    /** @return array{name: string} */
    private function categoryRecord(Category $category): array
    {
        return [
            'name' => (string) $category->name,
        ];
    }

    /** @return array<string, mixed> */
    private function mapService(Service $service): array
    {
        return $this->serviceRecord($service);
    }

    /** @return array{name: string, duration_minutes: int, price_cents: int} */
    private function serviceRecord(Service $service): array
    {
        return [
            'name' => (string) $service->name,
            'duration_minutes' => (int) $service->duration_minutes,
            'price_cents' => (int) $service->price_cents,
        ];
    }

    /** @return array<string, mixed> */
    private function mapProfessional(Professional $professional): array
    {
        return $this->professionalRecord($professional);
    }

    /** @return array{name: string} */
    private function professionalRecord(Professional $professional): array
    {
        return [
            'name' => (string) $professional->name,
        ];
    }

    private function unit(TenantContext $context): Unit
    {
        if (! $context->unit instanceof Unit) {
            throw new AuthorizationException('An explicit unit context is required for catalog reads.');
        }

        return $context->unit;
    }
}
