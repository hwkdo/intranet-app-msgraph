<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class OnenoteSourceModifiedAt
{
    public static function same(?CarbonInterface $stored, string $remote): bool
    {
        if (! $stored instanceof CarbonInterface) {
            return false;
        }

        $remoteUtc = self::utc($remote);

        if ($stored->copy()->utc()->startOfSecond()->equalTo($remoteUtc)) {
            return true;
        }

        return self::legacyUtc($stored)->equalTo($remoteUtc);
    }

    /**
     * Ältere Läufe haben den UTC-Zeitstempel ohne Offset gespeichert.
     * Eloquent liest das naive Datum in der App-Zeitzone, der Vergleich wäre sonst immer falsch.
     */
    public static function needsRepair(?CarbonInterface $stored, string $remote): bool
    {
        if (! $stored instanceof CarbonInterface) {
            return false;
        }

        if ($stored->copy()->utc()->startOfSecond()->equalTo(self::utc($remote))) {
            return false;
        }

        return self::legacyUtc($stored)->equalTo(self::utc($remote));
    }

    private static function utc(string $value): Carbon
    {
        return Carbon::parse($value)->utc()->startOfSecond();
    }

    private static function legacyUtc(CarbonInterface $stored): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $stored->format('Y-m-d H:i:s'),
            'UTC',
        )->startOfSecond();
    }
}
