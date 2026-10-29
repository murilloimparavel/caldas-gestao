<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationPassportServiceProvider;
use App\Providers\TelemetryServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationPassportServiceProvider::class,
    TelemetryServiceProvider::class,
];
