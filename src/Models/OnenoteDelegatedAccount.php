<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Models;

use Illuminate\Database\Eloquent\Model;

class OnenoteDelegatedAccount extends Model
{
    public const KEY = 'onenote';

    protected $table = 'intranet_app_msgraph_onenote_accounts';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
        ];
    }
}
