<?php

namespace App\Support\Integrations;

use App\Models\Integrations\ProposedOperation;

final class ProposalConfirmationUrl
{
    public static function for(ProposedOperation $proposal): string
    {
        $relativePath = route('integration-proposals.show', $proposal, absolute: false);

        return rtrim((string) config('app.url'), '/').'/'.ltrim($relativePath, '/');
    }
}
