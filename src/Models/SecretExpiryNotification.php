<?php

namespace Hwkdo\IntranetAppMsgraph\Models;

use Illuminate\Database\Eloquent\Model;

class SecretExpiryNotification extends Model
{
    protected $table = 'intranet_app_msgraph_secret_expiry_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_warning_sent_at' => 'datetime',
            'last_warning_sent_at' => 'datetime',
        ];
    }

    public static function findOrCreateForKey(string $keyId, ?string $applicationId = null): self
    {
        $record = self::query()->where('key_id', $keyId)->first();

        if ($record !== null) {
            return $record;
        }

        return self::query()->create([
            'key_id' => $keyId,
            'application_id' => $applicationId,
        ]);
    }
}
