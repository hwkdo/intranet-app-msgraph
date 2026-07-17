<?php

use Hwkdo\IntranetAppMsgraph\Jobs\CheckAzureAppSecretsExpiry;
use Hwkdo\IntranetAppMsgraph\Jobs\ProcessAuslandseinsatzMemberships;
use Illuminate\Support\Facades\Schedule;

// In Tests überspringe das Laden der Settings
if (! app()->runningUnitTests()) {

    Schedule::job(new ProcessAuslandseinsatzMemberships)
        ->everyFourHours();

    Schedule::job(new CheckAzureAppSecretsExpiry)
        ->dailyAt('06:00');
}
