<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Models;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Illuminate\Database\Eloquent\Model;

class OnenoteRagPage extends Model
{
    protected $table = 'intranet_app_msgraph_onenote_pages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OnenoteRagStatus::class,
            'indexed_at' => 'datetime',
        ];
    }
}
