<?php

namespace Hwkdo\IntranetAppMsgraph\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuslandseinsatzMembership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<AuslandseinsatzMembership>  $query
     * @return Builder<AuslandseinsatzMembership>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }

    /**
     * @param  Builder<AuslandseinsatzMembership>  $query
     * @return Builder<AuslandseinsatzMembership>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('removed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
