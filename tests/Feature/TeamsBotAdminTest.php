<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\Livewire\TeamsBotAdmin;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface;
use Livewire\Livewire;

it('dispatches channel test message job from teams bot admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(1);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('sendChannelMessage')
        ->once()
        ->with('team-1', 'channel-1', 'Hallo Kanal');

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('selectedTeam', [
            'teamId' => 'team-1',
            'teamName' => 'Test Team',
        ])
        ->set('selectedChannelId', 'channel-1')
        ->set('channelTestMessage', 'Hallo Kanal')
        ->call('sendChannelTestMessage')
        ->assertHasNoErrors();
});

it('searches tenant teams from teams bot admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('searchTenantTeams')
        ->once()
        ->with('marketing')
        ->andReturn([
            ['teamId' => 'team-1', 'teamName' => 'Marketing'],
        ]);

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('teamSearch', 'marketing')
        ->assertSet('teamSearchResults', [
            ['teamId' => 'team-1', 'teamName' => 'Marketing'],
        ]);
});

it('installs the bot for the selected team from the admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('installForTeam')
        ->once()
        ->with('team-1');

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('selectedTeam', ['teamId' => 'team-1', 'teamName' => 'Marketing'])
        ->call('installBotForTeam')
        ->assertHasNoErrors();
});

it('loads channels after selecting a team in the admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('listTeamChannels')
        ->once()
        ->with('team-1')
        ->andReturn([
            ['channelId' => 'channel-1', 'channelName' => 'General'],
        ]);

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->call('selectTeam', 'team-1', 'Marketing')
        ->assertSet('selectedTeam', ['teamId' => 'team-1', 'teamName' => 'Marketing'])
        ->assertSet('teamChannels', [
            ['channelId' => 'channel-1', 'channelName' => 'General'],
        ]);
});

it('loads group chats after selecting a chat user in the admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('listUserGroupChats')
        ->once()
        ->with('azure-123')
        ->andReturn([
            ['chatId' => '19:abc@thread.v2', 'label' => 'Projekt-Team'],
        ]);

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->call('selectChatUser', 'max@example.com', 'Max Mustermann', 'azure-123')
        ->assertSet('groupChats', [
            ['chatId' => '19:abc@thread.v2', 'label' => 'Projekt-Team'],
        ]);
});

it('installs the bot for the selected group chat from the admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('installForChat')
        ->once()
        ->with('19:abc@thread.v2');

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('selectedChatId', '19:abc@thread.v2')
        ->call('installBotForChat')
        ->assertHasNoErrors();
});

it('dispatches group chat test message from teams bot admin component', function (): void {
    $teamsBot = Mockery::mock(MsGraphTeamsBotServiceInterface::class);
    $teamsBot->shouldReceive('isEnabled')->andReturn(true);
    $teamsBot->shouldReceive('activeConversationCount')->andReturn(0);
    $teamsBot->shouldReceive('listConversations')->andReturn(collect());
    $teamsBot->shouldReceive('getSdkRestHealthStatus')->andReturn([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
    $teamsBot->shouldReceive('sendChatMessage')
        ->once()
        ->with('19:abc@thread.v2', 'Hallo Gruppenchat');

    app()->instance(MsGraphTeamsBotServiceInterface::class, $teamsBot);

    Livewire::test(TeamsBotAdmin::class)
        ->set('selectedChatId', '19:abc@thread.v2')
        ->set('chatTestMessage', 'Hallo Gruppenchat')
        ->call('sendChatTestMessage')
        ->assertHasNoErrors();
});

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
