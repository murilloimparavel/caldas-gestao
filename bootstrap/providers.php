<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationPassportServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationPassportServiceProvider::class,
];
