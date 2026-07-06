<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\Livewire\TeamsBotAdmin;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface;
use Livewire\Livewire;

it('dispatches test message job from teams bot admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(1);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('sendMessage')
        ->once()
        ->with('azure-123', 'Hallo aus dem Admin');

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('selectedUser', [
            'id' => 'azure-123',
            'upn' => 'max@example.com',
            'displayName' => 'Max Mustermann',
        ])
        ->set('testMessage', 'Hallo aus dem Admin')
        ->call('sendTestMessage')
        ->assertHasNoErrors();
});

it('queues install job from teams bot admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('installForUser')
        ->once()
        ->with('azure-456', 'user@example.com', 'User Example');

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('selectedUser', [
            'id' => 'azure-456',
            'upn' => 'user@example.com',
            'displayName' => 'User Example',
        ])
        ->call('installBot')
        ->assertHasNoErrors();
});

it('shows teams-sdk-rest health status in admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(2);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => false,
        'service' => null,
        'base_url' => 'http://teams-sdk-rest.test',
    ]);

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->assertSee('teams-sdk-rest')
        ->assertSee('Nicht erreichbar')
        ->assertSee('http://teams-sdk-rest.test');
});

it('refreshes teams-sdk-rest health status from admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')
        ->twice()
        ->andReturn([
            'healthy' => true,
            'service' => 'teams-sdk-rest',
            'base_url' => 'http://teams-sdk-rest.test',
        ]);

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->call('refreshSdkHealth')
        ->assertHasNoErrors();
});
