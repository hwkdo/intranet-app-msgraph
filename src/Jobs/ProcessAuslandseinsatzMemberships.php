<?php

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Hwkdo\IntranetAppMsgraph\Models\AuslandseinsatzMembership;
use Hwkdo\IntranetAppMsgraph\Models\IntranetAppMsgraphSettings;
use Hwkdo\IntranetAppMsgraph\Services\GraphGroupService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAuslandseinsatzMemberships implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $settings = IntranetAppMsgraphSettings::current();
        $groupId = $settings?->settings?->gruppenIdAuslandseinsatz ?? '7d01d307-f7be-4b9c-a108-f40ca4844f29';

        $groupService = app(GraphGroupService::class);
        $userService = app(MsGraphUserServiceInterface::class);

        AuslandseinsatzMembership::query()->dueToActivate()->each(function (AuslandseinsatzMembership $membership) use ($groupId, $groupService): void {
            if (! $membership->azure_user_id) {
                Log::error('Auslandseinsatz: Keine Azure-User-ID für '.$membership->upn.' — Aktivierung übersprungen.');

                return;
            }

            $success = $groupService->addUserToGroup($groupId, $membership->azure_user_id);

            if ($success) {
                $membership->markAsActivated();
                Log::info('Auslandseinsatz: Geplanter Eintrag für '.$membership->upn.' aktiviert.');
            } else {
                Log::error('Auslandseinsatz: Fehler beim Aktivieren von '.$membership->upn.' in Gruppe '.$groupId);
            }
        });

        AuslandseinsatzMembership::query()->dueToDeactivate()->each(function (AuslandseinsatzMembership $membership) use ($groupId, $userService): void {
            $success = $userService->removeUserFromGroup($membership->upn, $groupId);

            if ($success) {
                $membership->markAsRemoved();
                Log::info('Auslandseinsatz: Mitgliedschaft für '.$membership->upn.' abgelaufen und entfernt.');
            } else {
                Log::error('Auslandseinsatz: Fehler beim Entfernen von '.$membership->upn.' aus Gruppe '.$groupId);
            }
        });
    }
}
