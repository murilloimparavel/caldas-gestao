<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Customer;
use App\Models\PackageTemplate;
use App\Models\Product;
use App\Models\Professional;
use App\Models\SaleCategory;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class SelectorOptionsService
{
    /**
     * @var array<string, array{model: class-string<Model>, columns: list<string>, active: string}>
     */
    private const RESOURCES = [
        'customers' => [
            'model' => Customer::class,
            'columns' => ['id', 'name', 'phone'],
            'active' => 'status',
        ],
        'professionals' => [
            'model' => Professional::class,
            'columns' => ['id', 'name', 'phone'],
            'active' => 'status',
        ],
        'services' => [
            'model' => Service::class,
            'columns' => ['id', 'name', 'duration_minutes', 'price_cents', 'category_id'],
            'active' => 'status',
        ],
        'products' => [
            'model' => Product::class,
            'columns' => ['id', 'name', 'sale_price_cents', 'current_stock', 'category_id'],
            'active' => 'is_active',
        ],
        'inventory-products' => [
            'model' => Product::class,
            'columns' => ['id', 'name', 'current_stock', 'min_stock', 'unit_of_measure', 'lock_version', 'cost_price_cents'],
            'active' => 'is_active',
        ],
        'suppliers' => [
            'model' => Supplier::class,
            'columns' => ['id', 'name', 'trade_name', 'phone', 'email'],
            'active' => 'is_active',
        ],
        'categories' => [
            'model' => Category::class,
            'columns' => ['id', 'name', 'type'],
            'active' => 'is_active',
        ],
        'sale-categories' => [
            'model' => SaleCategory::class,
            'columns' => ['id', 'name', 'type', 'uniqueness_scope'],
            'active' => 'is_active',
        ],
        'packages' => [
            'model' => PackageTemplate::class,
            'columns' => ['id', 'name', 'price_cents', 'total_sessions', 'validity_days'],
            'active' => 'is_active',
        ],
        'subscription-plans' => [
            'model' => SubscriptionPlan::class,
            'columns' => ['id', 'name', 'price_cents', 'billing_cycle'],
            'active' => 'is_active',
        ],
    ];

    /** @return class-string<Model> */
    public function modelFor(string $resource): string
    {
        return self::RESOURCES[$resource]['model'];
    }

    /**
     * @return array<string, mixed>
     */
    public function optionFor(string $resource, Model $option): array
    {
        if ($resource === 'inventory-products') {
            return collect(self::RESOURCES[$resource]['columns'])
                ->mapWithKeys(fn (string $column): array => [$column => $option->getAttribute($column)])
                ->all();
        }

        return collect(self::RESOURCES[$resource]['columns'])
            ->mapWithKeys(fn (string $column): array => [$column => $option->getAttribute($column)])
            ->all();
    }

    /**
     * @param  array{resource: string, search?: string, category_id?: string|null, page?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(TenantContext $context, array $filters): LengthAwarePaginator
    {
        $resource = $filters['resource'];
        $definition = self::RESOURCES[$resource];
        $modelClass = $definition['model'];
        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        /** @var Builder<Model> $query */
        $query = $modelClass::query()
            ->select($definition['columns'])
            ->where('tenant_id', $tenantId)
            ->where($definition['active'], $definition['active'] === 'status' ? 'active' : true)
            ->when($resource === 'suppliers', function (Builder $supplierQuery) use ($unitId): void {
                $supplierQuery->where(function (Builder $unitQuery) use ($unitId): void {
                    $unitQuery->whereNull('unit_id');

                    if ($unitId !== null) {
                        $unitQuery->orWhere('unit_id', $unitId);
                    }
                });
            }, function (Builder $unitQuery) use ($unitId): void {
                $unitQuery->where('unit_id', $unitId);
            })
            ->when($resource === 'professionals' && $context->membership->professional_id !== null, fn (Builder $professionalQuery): Builder => $professionalQuery->whereKey($context->membership->professional_id))
            ->when(
                in_array($resource, ['services', 'products'], true) ? ($filters['category_id'] ?? null) : null,
                fn (Builder $categoryQuery, string $categoryId): Builder => $categoryQuery->where('category_id', $categoryId),
            )
            ->when($filters['search'] ?? null, function (Builder $searchQuery, string $search) use ($resource): void {
                $normalizedSearch = Str::lower(Str::ascii(trim($search)));

                if ($normalizedSearch === '') {
                    return;
                }

                $searchQuery->where(function (Builder $nameQuery) use ($normalizedSearch, $resource): void {
                    $nameQuery->whereRaw($this->unaccentedSelectorColumn('name').' LIKE ?', ["%{$normalizedSearch}%"]);

                    if (in_array($resource, ['customers', 'professionals'], true)) {
                        $phoneSearch = preg_replace('/\D+/', '', $normalizedSearch) ?? '';

                        if ($phoneSearch !== '') {
                            if ($resource === 'customers') {
                                $nameQuery->orWhere('phone_normalized', 'like', "%{$phoneSearch}%");
                            }

                            $nameQuery->orWhereRaw($this->normalizedPhoneColumn('phone').' LIKE ?', ["%{$phoneSearch}%"]);
                        }

                        $nameQuery->orWhereRaw($this->unaccentedSelectorColumn('email').' LIKE ?', ["%{$normalizedSearch}%"]);
                    }

                    if ($resource === 'suppliers') {
                        $phoneSearch = preg_replace('/\D+/', '', $normalizedSearch) ?? '';
                        $nameQuery->orWhereRaw($this->unaccentedSelectorColumn('trade_name').' LIKE ?', ["%{$normalizedSearch}%"]);
                        $nameQuery->orWhereRaw($this->unaccentedSelectorColumn('email').' LIKE ?', ["%{$normalizedSearch}%"]);

                        if ($phoneSearch !== '') {
                            $nameQuery->orWhereRaw($this->normalizedPhoneColumn('phone').' LIKE ?', ["%{$phoneSearch}%"]);
                        }
                    }
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        return $query->paginate(
            perPage: $filters['per_page'] ?? 25,
            page: $filters['page'] ?? 1,
        );
    }

    /**
     * @return literal-string
     */
    private function unaccentedSelectorColumn(string $column): string
    {
        return match ($column) {
            'name' => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(name), 'á', 'a'), 'à', 'a'), 'â', 'a'), 'ã', 'a'), 'ä', 'a'), 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'í', 'i'), 'ì', 'i'), 'î', 'i'), 'ï', 'i'), 'ó', 'o'), 'ò', 'o'), 'ô', 'o'), 'õ', 'o'), 'ö', 'o'), 'ú', 'u'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), 'ç', 'c'), 'ñ', 'n')",
            'email' => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(email), 'á', 'a'), 'à', 'a'), 'â', 'a'), 'ã', 'a'), 'ä', 'a'), 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'í', 'i'), 'ì', 'i'), 'î', 'i'), 'ï', 'i'), 'ó', 'o'), 'ò', 'o'), 'ô', 'o'), 'õ', 'o'), 'ö', 'o'), 'ú', 'u'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), 'ç', 'c'), 'ñ', 'n')",
            'trade_name' => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(trade_name), 'á', 'a'), 'à', 'a'), 'â', 'a'), 'ã', 'a'), 'ä', 'a'), 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'í', 'i'), 'ì', 'i'), 'î', 'i'), 'ï', 'i'), 'ó', 'o'), 'ò', 'o'), 'ô', 'o'), 'õ', 'o'), 'ö', 'o'), 'ú', 'u'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), 'ç', 'c'), 'ñ', 'n')",
            default => throw new \InvalidArgumentException("Unsupported selector column [{$column}]."),
        };
    }

    /**
     * @return literal-string
     */
    private function normalizedPhoneColumn(string $column): string
    {
        return match ($column) {
            'phone' => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '(', ''), ')', ''), '-', ''), '+', ''), '.', ''), '/', '')",
            default => throw new \InvalidArgumentException("Unsupported selector phone column [{$column}]."),
        };
    }
}
