<?php

declare(strict_types=1);

namespace App\Mcp;

use LogicException;

final class CatalogPagination
{
    /** @return array{items: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, has_more: bool}} */
    public static function from(mixed $paginator): array
    {
        if (! is_object($paginator)
            || ! method_exists($paginator, 'items')
            || ! method_exists($paginator, 'currentPage')
            || ! method_exists($paginator, 'perPage')
            || ! method_exists($paginator, 'hasMorePages')) {
            throw new LogicException('The catalog query must return a simple paginator.');
        }

        $items = $paginator->items();
        if (! is_array($items)) {
            throw new LogicException('The catalog paginator items must be an array.');
        }

        $records = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new LogicException('The catalog paginator items must be arrays.');
            }

            $records[] = $item;
        }

        return [
            'items' => $records,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
