<?php

namespace Database\Factories;

use App\Models\RetentionCampaignRecipient;
use App\Models\RetentionDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RetentionDelivery>
 */
class RetentionDeliveryFactory extends Factory
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
            'retention_campaign_recipient_id' => RetentionCampaignRecipient::factory(),
            'retention_campaign_id' => fn (array $attributes): string => (string) RetentionCampaignRecipient::query()->whereKey($attributes['retention_campaign_recipient_id'])->value('retention_campaign_id'),
            'tenant_id' => fn (array $attributes): string => (string) RetentionCampaignRecipient::query()->whereKey($attributes['retention_campaign_recipient_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) RetentionCampaignRecipient::query()->whereKey($attributes['retention_campaign_recipient_id'])->value('unit_id'),
            'customer_id' => fn (array $attributes): string => (string) RetentionCampaignRecipient::query()->whereKey($attributes['retention_campaign_recipient_id'])->value('customer_id'),
            'channel' => fn (array $attributes): string => (string) RetentionCampaignRecipient::query()->whereKey($attributes['retention_campaign_recipient_id'])->value('channel'),
            'status' => 'pending',
            'idempotency_key' => 'retention-delivery-'.Str::uuid7(),
            'attempts' => 0,
            'available_at' => now(),
        ];
    }
}
