<?php

use Hwkdo\IntranetAppMsgraph\Jobs\RemoveExpiredAuslandseinsatzMemberships;
use Illuminate\Support\Facades\Schedule;

// In Tests überspringe das Laden der Settings
if (! app()->runningUnitTests()) {

    Schedule::job(new RemoveExpiredAuslandseinsatzMemberships())
        ->dailyAt('00:01');
}