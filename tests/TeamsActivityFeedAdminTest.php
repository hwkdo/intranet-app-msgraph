<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\LivewireAdmin\TeamsActivityFeedAdmin;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsActivityFeedServiceInterface;
use Livewire\Livewire;

it('dispatches activity feed notification from admin component', function (): void {
    $activityFeed = Mockery::mock(MsGraphTeamsActivityFeedServiceInterface::class);
    $activityFeed->shouldReceive('isEnabled')->andReturn(true);
    $activityFeed->shouldReceive('sendNotification')
        ->once()
        ->with(
            'azure-123',
            'Vorschau Test',
            'Actor Zeile',
            'Mein Thema',
            'https://intranet.test',
        );

    app()->instance(MsGraphTeamsActivityFeedServiceInterface::class, $activityFeed);

    Livewire::test(TeamsActivityFeedAdmin::class)
        ->set('selectedUser', [
            'id' => 'azure-123',
            'upn' => 'max@example.com',
            'displayName' => 'Max Mustermann',
        ])
        ->set('previewText', 'Vorschau Test')
        ->set('actorText', 'Actor Zeile')
        ->set('topicTitle', 'Mein Thema')
        ->set('webUrl', 'https://intranet.test')
        ->call('sendTestNotification')
        ->assertHasNoErrors();
});

it('requires selected user before sending activity feed notification', function (): void {
    $activityFeed = Mockery::mock(MsGraphTeamsActivityFeedServiceInterface::class);
    $activityFeed->shouldReceive('isEnabled')->andReturn(true);
    $activityFeed->shouldNotReceive('sendNotification');

    app()->instance(MsGraphTeamsActivityFeedServiceInterface::class, $activityFeed);

    Livewire::test(TeamsActivityFeedAdmin::class)
        ->set('previewText', 'Ohne User')
        ->call('sendTestNotification')
        ->assertHasNoErrors();
});
