<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Integrations\ProposedOperation;

class ProposedOperationRepository
{
    public function findOrFail(string $id): ProposedOperation
    {
        return ProposedOperation::query()->whereKey($id)->firstOrFail();
    }
}
