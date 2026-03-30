<?php

namespace Hwkdo\IntranetAppMsgraph\Support;

use Carbon\Carbon;

final class UpcomingAzureAppSecretsList
{
    /**
     * Flacht App-Listen aus Microsoft Graph ab, behält nur Credentials mit künftigem Ablauf
     * (noch nicht abgelaufen), sortiert nach frühestem Ablauf und begrenzt die Anzahl.
     *
     * @param  array<int, array{id: string, appId: string, displayName: string, secrets: array<int, array<string, mixed>>}>  $apps
     * @return list<array{
     *     appDisplayName: string,
     *     appObjectId: string,
     *     appId: string,
     *     secretDisplayName: string,
     *     credentialType: string,
     *     endDateTime: Carbon,
     *     daysUntilExpiry: int
     * }>
     */
    public static function nextExpiring(array $apps, int $limit = 5): array
    {
        $rows = [];

        foreach ($apps as $app) {
            foreach ($app['secrets'] ?? [] as $secret) {
                $days = $secret['daysUntilExpiry'] ?? null;
                if ($days === null) {
                    continue;
                }

                if ($days < 0) {
                    continue;
                }

                $end = $secret['endDateTime'] ?? null;
                if (! $end instanceof Carbon) {
                    continue;
                }

                $rows[] = [
                    'appDisplayName' => (string) ($app['displayName'] ?? ''),
                    'appObjectId' => (string) ($app['id'] ?? ''),
                    'appId' => (string) ($app['appId'] ?? ''),
                    'secretDisplayName' => (string) ($secret['displayName'] ?? ''),
                    'credentialType' => (string) ($secret['credentialType'] ?? 'secret'),
                    'endDateTime' => $end,
                    'daysUntilExpiry' => (int) $days,
                ];
            }
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => $a['endDateTime']->getTimestamp() <=> $b['endDateTime']->getTimestamp(),
        );

        if (count($rows) <= $limit) {
            return $rows;
        }

        return array_slice($rows, 0, $limit);
    }
}
