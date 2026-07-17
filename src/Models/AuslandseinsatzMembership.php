<?php

namespace Hwkdo\IntranetAppMsgraph\Models;

use Hwkdo\IntranetAppMsgraph\Enums\AuslandseinsatzMembershipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuslandseinsatzMembership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'activated_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function status(): AuslandseinsatzMembershipStatus
    {
        if ($this->removed_at !== null) {
            return AuslandseinsatzMembershipStatus::Entfernt;
        }

        if ($this->activated_at === null) {
            return AuslandseinsatzMembershipStatus::Geplant;
        }

        if ($this->ends_at->startOfDay()->lt(now()->startOfDay())) {
            return AuslandseinsatzMembershipStatus::Abgelaufen;
        }

        return AuslandseinsatzMembershipStatus::Aktiv;
    }

    public function isActivated(): bool
    {
        return $this->activated_at !== null;
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
     * Geplante Aufenthalte, deren Zeitraum heute beginnt oder läuft (noch nicht abgelaufen).
     *
     * @param  Builder<AuslandseinsatzMembership>  $query
     * @return Builder<AuslandseinsatzMembership>
     */
    public function scopeDueToActivate(Builder $query): Builder
    {
        return $query->whereNull('removed_at')
            ->whereNull('activated_at')
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->whereDate('ends_at', '>=', now()->toDateString());
    }

    /**
     * Geplante Aufenthalte, die nie aktiviert wurden und deren Enddatum vorbei ist.
     * Diese dürfen nicht erst in die Gruppe und danach wieder entfernt werden.
     *
     * @param  Builder<AuslandseinsatzMembership>  $query
     * @return Builder<AuslandseinsatzMembership>
     */
    public function scopeExpiredWithoutActivation(Builder $query): Builder
    {
        return $query->whereNull('removed_at')
            ->whereNull('activated_at')
            ->whereDate('ends_at', '<', now()->toDateString());
    }

    /**
     * @param  Builder<AuslandseinsatzMembership>  $query
     * @return Builder<AuslandseinsatzMembership>
     */
    public function scopeDueToDeactivate(Builder $query): Builder
    {
        return $query->whereNull('removed_at')
            ->whereNotNull('activated_at')
            ->whereDate('ends_at', '<', now()->toDateString());
    }

    public function startsOnOrBeforeToday(): bool
    {
        return $this->starts_at->startOfDay()->lte(now()->startOfDay());
    }

    public function markAsRemoved(): void
    {
        $this->update(['removed_at' => now()]);
    }

    public function markAsActivated(): void
    {
        $this->update(['activated_at' => now()]);
    }
}
