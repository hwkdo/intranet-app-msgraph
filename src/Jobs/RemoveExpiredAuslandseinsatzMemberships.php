<?php

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Hwkdo\IntranetAppMsgraph\Models\AuslandseinsatzMembership;
use Hwkdo\IntranetAppMsgraph\Models\IntranetAppMsgraphSettings;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RemoveExpiredAuslandseinsatzMemberships implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $settings = IntranetAppMsgraphSettings::current();
        $groupId = $settings?->settings?->gruppenIdAuslandseinsatz ?? '7d01d307-f7be-4b9c-a108-f40ca4844f29';

        $userService = app(MsGraphUserServiceInterface::class);

        AuslandseinsatzMembership::query()->expired()->each(function (AuslandseinsatzMembership $membership) use ($groupId, $userService) {
            $success = $userService->removeUserFromGroup($membership->upn, $groupId);

            if ($success) {
                $membership->delete();
                Log::info('Auslandseinsatz: Mitgliedschaft für '.$membership->upn.' abgelaufen und entfernt.');
            } else {
                Log::error('Auslandseinsatz: Fehler beim Entfernen von '.$membership->upn.' aus Gruppe '.$groupId);
            }
        });
    }
}
