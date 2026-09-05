<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\RetentionCampaign;
use App\Models\RetentionCampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RetentionCampaignRecipient>
 */
class RetentionCampaignRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'retention_campaign_id' => RetentionCampaign::factory(),
            'customer_id' => function (array $attributes): string {
                $campaign = RetentionCampaign::query()->findOrFail($attributes['retention_campaign_id']);

                return (string) Customer::factory()->create([
                    'tenant_id' => $campaign->tenant_id,
                    'unit_id' => $campaign->unit_id,
                ])->getKey();
            },
            'tenant_id' => fn (array $attributes): string => (string) RetentionCampaign::query()->whereKey($attributes['retention_campaign_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) RetentionCampaign::query()->whereKey($attributes['retention_campaign_id'])->value('unit_id'),
            'channel' => fn (array $attributes): string => (string) RetentionCampaign::query()->whereKey($attributes['retention_campaign_id'])->value('channel'),
            'status' => 'selected',
            'selected_at' => now(),
        ];
    }
}
