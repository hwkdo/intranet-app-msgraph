<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @implements CastsAttributes<Carbon|null, string|null>
 */
final class OnenoteSourceModifiedAtCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value, (string) config('app.timezone'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $instant = $value instanceof CarbonInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value);

        return $instant
            ->timezone((string) config('app.timezone'))
            ->startOfSecond()
            ->format('Y-m-d H:i:s');
    }
}
