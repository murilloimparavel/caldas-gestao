<?php

use App\Actions\Dashboard\BuildDashboardSnapshot;

it('uses a safe empty dashboard snapshot before sources are connected', function () {
    expect((new BuildDashboardSnapshot)->handle())->toBe([
        'mode' => 'empty',
        'metrics' => [],
        'appointments' => [],
        'attentionItems' => [],
    ]);
});
