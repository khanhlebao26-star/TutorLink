<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\MonitoringServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    MonitoringServiceProvider::class,
];
