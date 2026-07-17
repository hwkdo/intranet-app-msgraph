<?php

use Hwkdo\IntranetAppMsgraph\Enums\AuslandseinsatzMembershipStatus;
use Hwkdo\IntranetAppMsgraph\Models\AuslandseinsatzMembership;

it('reports geplant status for future start without activation', function () {
    $membership = new AuslandseinsatzMembership([
        'starts_at' => now()->addWeek()->toDateString(),
        'ends_at' => now()->addWeeks(2)->toDateString(),
        'activated_at' => null,
        'removed_at' => null,
    ]);

    expect($membership->status())->toBe(AuslandseinsatzMembershipStatus::Geplant);
});

it('reports aktiv status when activated and end date is in the future', function () {
    $membership = new AuslandseinsatzMembership([
        'starts_at' => now()->subDay()->toDateString(),
        'ends_at' => now()->addWeek()->toDateString(),
        'activated_at' => now()->subDay(),
        'removed_at' => null,
    ]);

    expect($membership->status())->toBe(AuslandseinsatzMembershipStatus::Aktiv);
});

it('reports abgelaufen when end date is in the past', function () {
    $membership = new AuslandseinsatzMembership([
        'starts_at' => now()->subWeeks(2)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'activated_at' => now()->subWeeks(2),
        'removed_at' => null,
    ]);

    expect($membership->status())->toBe(AuslandseinsatzMembershipStatus::Abgelaufen);
});

it('detects when membership should start on or before today', function () {
    $membership = new AuslandseinsatzMembership([
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addWeek()->toDateString(),
    ]);

    expect($membership->startsOnOrBeforeToday())->toBeTrue();
});

it('scopes dueToActivate to memberships that have not ended yet', function () {
    $sql = AuslandseinsatzMembership::query()->dueToActivate()->toSql();

    expect($sql)->toContain('starts_at')
        ->and($sql)->toContain('ends_at')
        ->and($sql)->toContain('activated_at');
});

it('scopes expiredWithoutActivation to never-activated past stays', function () {
    $sql = AuslandseinsatzMembership::query()->expiredWithoutActivation()->toSql();

    expect($sql)->toContain('ends_at')
        ->and($sql)->toContain('activated_at');
});
