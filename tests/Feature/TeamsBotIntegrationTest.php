<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotMessageJob;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('ms-graph-laravel.teams_bot.enabled', true);
    config()->set('ms-graph-laravel.teams_bot.app_id', 'test-bot-app-id');
    config()->set('ms-graph-laravel.teams_bot.app_secret', 'test-bot-secret');
    config()->set('ms-graph-laravel.teams_sdk_rest.base_url', 'http://teams-sdk-rest.test');
    config()->set('ms-graph-laravel.teams_sdk_rest.api_key', 'test-api-key');

    if (! Schema::hasTable('ms_graph_laravel_teams_bot_conversations')) {
        (include base_path('vendor/hwkdo/ms-graph-laravel/database/migrations/create_ms_graph_laravel_teams_bot_conversations_table.php'))->up();
    }

    TeamsBotConversation::query()->delete();
});

it('marks a teams bot conversation as active', function (): void {
    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-1',
        'status' => TeamsBotConversationStatus::Pending,
    ]);

    $conversation->markActive('conversation-123', 'https://smba.trafficmanager.net/teams/');
    $conversation->refresh();

    expect($conversation->status)->toBe(TeamsBotConversationStatus::Active)
        ->and($conversation->isReadyForMessaging())->toBeTrue();
});

it('dispatches send teams bot message job', function (): void {
    Queue::fake();

    app(TeamsBotMessagingService::class)->queueMessage('azure-user-1', 'Hallo Test');

    Queue::assertPushed(SendTeamsBotMessageJob::class);
});

it('sends a teams bot message when conversation is active', function (): void {
    TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-active',
        'conversation_id' => 'conv-1',
        'service_url' => 'https://smba.trafficmanager.net/teams/',
        'status' => TeamsBotConversationStatus::Active,
    ]);

    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('sendMessage')->once()->andReturn([
        'messageId' => 'activity-1',
        'conversationId' => 'conv-1',
    ]);
    $sdkClient->shouldReceive('getConversationForUser')->andReturn(null);
    $sdkClient->shouldReceive('registerConversation')->once();
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    app(TeamsBotMessagingService::class)->sendMessageSync('azure-user-active', 'Testnachricht');

    expect(TeamsBotConversation::query()->where('azure_user_id', 'azure-user-active')->first()?->last_message_at)->not->toBeNull();
});

it('stores conversation on teams install.add webhook', function (): void {
    $this->postJson(
        route('ms-graph-laravel.teams-webhook'),
        [
            'event' => 'install.add',
            'activity' => [
                'from' => [
                    'id' => 'azure-human-1',
                    'aadObjectId' => 'azure-human-1',
                    'userPrincipalName' => 'max@example.com',
                ],
                'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
                'conversation' => ['id' => 'conv-webhook-1'],
            ],
            'conversationRef' => [
                'conversationId' => 'conv-webhook-1',
                'userAadId' => 'azure-human-1',
                'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
            ],
        ],
        ['X-Teams-Event' => 'install.add'],
    )->assertNoContent();

    $conversation = TeamsBotConversation::query()->where('azure_user_id', 'azure-human-1')->first();

    expect($conversation?->status)->toBe(TeamsBotConversationStatus::Active);
});

it('rejects teams webhook with invalid signature', function (): void {
    config()->set('ms-graph-laravel.teams_sdk_rest.webhook_secret', 'test-secret');

    $this->postJson(
        route('ms-graph-laravel.teams-webhook'),
        ['event' => 'message', 'activity' => []],
        [
            'X-Teams-Event' => 'message',
            'X-Teams-Signature' => 'sha256=invalid',
        ],
    )->assertForbidden();
});
