<?php

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * @deprecated Use ProcessAuslandseinsatzMemberships instead.
 */
class RemoveExpiredAuslandseinsatzMemberships implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        (new ProcessAuslandseinsatzMemberships)->handle();
    }
}
