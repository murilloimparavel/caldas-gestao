<?php

use Illuminate\Support\Facades\Schema;

it('supports nullable Belasis source fields and tenant-scoped uniqueness', function (): void {
    $columns = Schema::getColumnListing('financial_obligations');
    $columnDefinitions = collect(Schema::getColumns('financial_obligations'))
        ->keyBy('name');
    $indexes = collect(Schema::getIndexes('financial_obligations'));
    $sourceUnique = $indexes->firstWhere('name', 'financial_obligations_tenant_unit_source_unique');

    expect($columns)
        ->toContain('source_id')
        ->toContain('source_metadata')
        ->and($columnDefinitions['source_id']['nullable'])->toBeTrue()
        ->and($columnDefinitions['source_metadata']['nullable'])->toBeTrue()
        ->and($sourceUnique)->not->toBeNull()
        ->and($sourceUnique['unique'])->toBeTrue()
        ->and($sourceUnique['columns'])->toBe(['tenant_id', 'unit_id', 'source_id']);
});
