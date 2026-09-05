<?php

namespace App\Policies;

use App\Models\RetentionCampaign;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class RetentionCampaignPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'retention.view');
    }

    public function view(User $user, RetentionCampaign $campaign): bool
    {
        return $this->allows($user, 'retention.view', $campaign);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'retention.manage');
    }

    public function update(User $user, RetentionCampaign $campaign): bool
    {
        return $this->allows($user, 'retention.manage', $campaign);
    }

    private function allows(User $user, string $permission, ?RetentionCampaign $campaign = null): bool
    {
        try {
            $context = $campaign === null ? app(TenantContext::class) : TenantContext::forUser($user, $campaign->tenant_id, $campaign->unit_id);

            return $context->user->is($user) && ($campaign === null || $context->unit?->getKey() === $campaign->unit_id) && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
