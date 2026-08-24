<?php

namespace App\Actions\Dashboard;

class BuildDashboardSnapshot
{
    /**
     * Build the read contract used by the authenticated dashboard.
     *
     * @return array{
     *     mode: 'empty',
     *     metrics: list<array{label: string, value: string, detail: string, tone: 'brand'|'success'|'warning'|'neutral'}>,
     *     appointments: list<array{id: string, startsAt: string, client: string, service: string, professional: string, status: 'confirmed'|'waiting'|'in-service'}>,
     *     attentionItems: list<array{id: string, label: string, detail: string, level: 'high'|'medium'|'low'}>
     * }
     */
    public function handle(): array
    {
        return [
            'mode' => 'empty',
            'metrics' => [],
            'appointments' => [],
            'attentionItems' => [],
        ];
    }
}
