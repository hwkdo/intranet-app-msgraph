<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Events\OnenotePageStatusChanged;
use Hwkdo\IntranetAppMsgraph\Jobs\SyncOnenoteLightRagTrack;
use Hwkdo\IntranetAppMsgraph\Livewire\OneNoteRag;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('sendet den onenote status über den privaten kanal', function () {
    $event = new OnenotePageStatusChanged('page-1', 'processing', null);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('msgraph-onenote-rag')])
        ->and($event->broadcastAs())->toBe('onenote.page.status')
        ->and($event->broadcastWith())->toBe([
            'pageId' => 'page-1',
            'status' => 'processing',
            'error' => null,
        ]);
});

it('erlaubt den onenote kanal nur mit manage-app-msgraph', function () {
    Permission::findOrCreate('manage-app-msgraph', 'web');

    $user = User::factory()->create();
    $manager = User::factory()->create();
    $manager->givePermissionTo('manage-app-msgraph');

    $callback = app(BroadcastManager::class)->driver()->getChannels()['msgraph-onenote-rag'];

    expect($callback($user))->toBeFalse()
        ->and($callback($manager))->toBeTrue();
});

it('aktualisiert den status der offenen seite ohne neu zu laden', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage-app-msgraph');

    Livewire::actingAs($user)
        ->test(OneNoteRag::class)
        ->set('pages', [[
            'id' => 'page-1',
            'title' => 'Brandschutz',
            'contentUrl' => 'https://graph.microsoft.com/v1.0/users/owner/onenote/pages/page-1/content',
            'status' => 'pending',
            'statusLabel' => 'wartet',
            'error' => null,
            'canUpload' => false,
        ]])
        ->call('applyOnenotePageStatus', [
            'pageId' => 'page-1',
            'status' => 'processed',
            'error' => null,
        ])
        ->assertSet('pages.0.status', 'processed')
        ->assertSet('pages.0.statusLabel', 'in LightRAG')
        ->assertSee('in LightRAG')
        ->assertSet('notebooks', []);
});

it('meldet abgeschlossene lightrag verarbeitung', function () {
    config([
        'intranet-app-msgraph.lightrag.api_key' => 'test-key',
        'intranet-app-msgraph.lightrag.instances.team-meetings' => [
            'label' => 'Team-Meetings',
            'url' => 'https://lightrag-onenote.example',
        ],
    ]);

    Event::fake([OnenotePageStatusChanged::class]);

    Http::fake([
        'https://lightrag-onenote.example/documents/track_status/insert_1' => Http::response([
            'documents' => [[
                'id' => 'doc-1',
                'status' => 'processed',
            ]],
        ]),
    ]);

    $page = OnenoteRagPage::query()->create([
        'owner_type' => 'user',
        'owner_id' => 'user-1',
        'notebook_id' => 'nb-1',
        'notebook_name' => 'Teammeetings',
        'section_id' => 'sec-1',
        'section_name' => 'Oktober',
        'page_id' => 'page-1',
        'page_title' => 'Brandschutz',
        'lightrag_instance' => 'team-meetings',
        'track_id' => 'insert_1',
        'status' => OnenoteRagStatus::Processing,
    ]);

    (new SyncOnenoteLightRagTrack('page-1'))->handle(app(LightRagOnenoteClient::class));

    expect($page->refresh()->status)->toBe(OnenoteRagStatus::Processed)
        ->and($page->lightrag_doc_id)->toBe('doc-1');

    Event::assertDispatched(OnenotePageStatusChanged::class, function (OnenotePageStatusChanged $event): bool {
        return $event->pageId === 'page-1' && $event->status === 'processed';
    });
});
