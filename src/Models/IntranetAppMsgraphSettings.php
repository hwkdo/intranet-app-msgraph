<?php

namespace Hwkdo\IntranetAppMsgraph\Models;

use Hwkdo\IntranetAppMsgraph\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppMsgraphSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppMsgraphSettings
    {
        return self::orderBy('version', 'desc')->first();
    }
}
