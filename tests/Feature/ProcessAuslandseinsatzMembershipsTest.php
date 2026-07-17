<?php

use Hwkdo\IntranetAppMsgraph\Jobs\ProcessAuslandseinsatzMemberships;
use Hwkdo\IntranetAppMsgraph\Models\AuslandseinsatzMembership;
use Hwkdo\IntranetAppMsgraph\Services\GraphGroupService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;

it('removes expired never-activated memberships without calling graph add', function () {
    $membership = AuslandseinsatzMembership::query()->create([
        'upn' => 'past-planned@example.com',
        'user_display_name' => 'Past Planned',
        'added_by_upn' => 'admin@example.com',
        'starts_at' => now()->subWeeks(2)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'azure_user_id' => 'azure-past',
        'activated_at' => null,
        'removed_at' => null,
    ]);

    $groupService = Mockery::mock(GraphGroupService::class);
    $groupService->shouldNotReceive('addUserToGroup');
    $groupService->shouldNotReceive('getGroupMembers');
    app()->instance(GraphGroupService::class, $groupService);

    $userService = Mockery::mock(MsGraphUserServiceInterface::class);
    $userService->shouldNotReceive('removeUserFromGroup');
    app()->instance(MsGraphUserServiceInterface::class, $userService);

    (new ProcessAuslandseinsatzMemberships)->handle();

    $membership->refresh();

    expect($membership->removed_at)->not->toBeNull()
        ->and($membership->activated_at)->toBeNull();
});

it('activates memberships that are due and still within range', function () {
    $membership = AuslandseinsatzMembership::query()->create([
        'upn' => 'due@example.com',
        'user_display_name' => 'Due User',
        'added_by_upn' => 'admin@example.com',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addWeek()->toDateString(),
        'azure_user_id' => 'azure-due',
        'activated_at' => null,
        'removed_at' => null,
    ]);

    $groupService = Mockery::mock(GraphGroupService::class);
    $groupService->shouldReceive('addUserToGroup')
        ->once()
        ->with(Mockery::type('string'), 'azure-due')
        ->andReturn(true);
    app()->instance(GraphGroupService::class, $groupService);

    $userService = Mockery::mock(MsGraphUserServiceInterface::class);
    $userService->shouldNotReceive('removeUserFromGroup');
    app()->instance(MsGraphUserServiceInterface::class, $userService);

    (new ProcessAuslandseinsatzMemberships)->handle();

    expect($membership->fresh()->activated_at)->not->toBeNull();
});

it('marks membership removed when graph remove fails but user is not in group', function () {
    $membership = AuslandseinsatzMembership::query()->create([
        'upn' => 'stuck@example.com',
        'user_display_name' => 'Stuck User',
        'added_by_upn' => 'admin@example.com',
        'starts_at' => now()->subWeeks(2)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'azure_user_id' => 'azure-stuck',
        'activated_at' => now()->subWeeks(2),
        'removed_at' => null,
    ]);

    $groupService = Mockery::mock(GraphGroupService::class);
    $groupService->shouldReceive('getGroupMembers')
        ->once()
        ->andReturn([]);
    app()->instance(GraphGroupService::class, $groupService);

    $userService = Mockery::mock(MsGraphUserServiceInterface::class);
    $userService->shouldReceive('removeUserFromGroup')
        ->once()
        ->andReturn(false);
    app()->instance(MsGraphUserServiceInterface::class, $userService);

    (new ProcessAuslandseinsatzMemberships)->handle();

    expect($membership->fresh()->removed_at)->not->toBeNull();
});

it('keeps membership when graph remove fails and user is still in group', function () {
    $membership = AuslandseinsatzMembership::query()->create([
        'upn' => 'still-in@example.com',
        'user_display_name' => 'Still In',
        'added_by_upn' => 'admin@example.com',
        'starts_at' => now()->subWeeks(2)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'azure_user_id' => 'azure-still',
        'activated_at' => now()->subWeeks(2),
        'removed_at' => null,
    ]);

    $groupService = Mockery::mock(GraphGroupService::class);
    $groupService->shouldReceive('getGroupMembers')
        ->once()
        ->andReturn([
            [
                'id' => 'azure-still',
                'upn' => 'still-in@example.com',
                'displayName' => 'Still In',
            ],
        ]);
    app()->instance(GraphGroupService::class, $groupService);

    $userService = Mockery::mock(MsGraphUserServiceInterface::class);
    $userService->shouldReceive('removeUserFromGroup')
        ->once()
        ->andReturn(false);
    app()->instance(MsGraphUserServiceInterface::class, $userService);

    (new ProcessAuslandseinsatzMemberships)->handle();

    expect($membership->fresh()->removed_at)->toBeNull();
});
