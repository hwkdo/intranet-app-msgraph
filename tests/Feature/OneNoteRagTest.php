<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Events\OnenotePageStatusChanged;
use Hwkdo\IntranetAppMsgraph\Jobs\QueueOnenotePagesForLightRag;
use Hwkdo\IntranetAppMsgraph\Jobs\SyncOnenoteLightRagTrack;
use Hwkdo\IntranetAppMsgraph\Jobs\UploadOnenotePageToLightRag;
use Hwkdo\IntranetAppMsgraph\Livewire\OneNoteRag;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Hwkdo\IntranetAppMsgraph\Services\OnenotePageUploadQueue;
use Hwkdo\IntranetAppMsgraph\Services\OnenoteDelegatedTokenService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Livewire\Livewire;
use Microsoft\Graph\Generated\Models\User as GraphUser;

it('zeigt onenote seiten und legt den upload an', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage-app-msgraph');

    $graphUser = Mockery::mock(GraphUser::class);
    $graphUser->shouldReceive('getId')->andReturn('user-1');

    $userService = Mockery::mock(MsGraphUserServiceInterface::class);
    $userService->shouldReceive('getUserByUpn')->once()->with('team@example.com')->andReturn($graphUser);
    app()->instance(MsGraphUserServiceInterface::class, $userService);

    $oneNote = Mockery::mock(MsGraphOneNoteServiceInterface::class);
    $oneNote->shouldReceive('listNotebooks')->once()->with(Mockery::type(Authenticatable::class), 'user', 'user-1')->andReturn([
        [
            'id' => 'nb-1',
            'name' => 'Teammeetings',
            'sectionsUrl' => 'https://graph.microsoft.com/v1.0/users/owner/onenote/notebooks/nb-1/sections',
            'sectionGroupsUrl' => 'https://graph.microsoft.com/v1.0/users/owner/onenote/notebooks/nb-1/sectionGroups',
            'webUrl' => 'https://contoso.sharepoint.com/teams/notebook',
        ],
    ]);
    $oneNote->shouldReceive('listSections')->once()->andReturn([
        'requestUrl' => 'https://graph.microsoft.com/v1.0/sites/site/onenote/notebooks/nb-1/sections',
        'sections' => [
            [
                'id' => 'sec-1',
                'name' => 'Oktober',
                'pagesUrl' => 'https://graph.microsoft.com/v1.0/users/owner/onenote/sections/sec-1/pages',
            ],
        ],
    ]);
    $oneNote->shouldReceive('listPages')->once()->andReturn([
        'requestUrl' => 'https://graph.microsoft.com/v1.0/sites/site/onenote/notebooks/nb-1/sections/sec-1/pages',
        'pages' => [
            [
                'id' => 'page-1',
                'title' => 'Brandschutz',
                'contentUrl' => 'https://graph.microsoft.com/v1.0/users/owner/onenote/pages/page-1/content',
            ],
        ],
    ]);
    app()->instance(MsGraphOneNoteServiceInterface::class, $oneNote);

    Bus::fake();

    Livewire::actingAs($user)
        ->test(OneNoteRag::class)
        ->assertSee('Microsoft-Anmeldung starten')
        ->set('ownerQuery', 'team@example.com')
        ->call('loadNotebooks')
        ->assertSet('notebooks.0.name', 'Teammeetings')
        ->call('selectNotebook', 'nb-1')
        ->assertSee('https://graph.microsoft.com/v1.0/sites/site/onenote/notebooks/nb-1/sections')
        ->call('selectSection', 'sec-1')
        ->assertSee('https://graph.microsoft.com/v1.0/sites/site/onenote/notebooks/nb-1/sections/sec-1/pages')
        ->assertSee('Brandschutz')
        ->assertSee('nicht hochgeladen')
        ->call('uploadPage', 'page-1')
        ->assertSee('wartet');

    expect(OnenoteRagPage::query()->where('page_id', 'page-1')->value('status'))->toBe('pending');

    Bus::assertDispatched(UploadOnenotePageToLightRag::class, function (UploadOnenotePageToLightRag $job) use ($user): bool {
        return $job->actorId === $user->id
            && $job->pageId === 'page-1'
            && $job->pageTitle === 'Brandschutz'
            && $job->contentUrl === 'https://graph.microsoft.com/v1.0/users/owner/onenote/pages/page-1/content'
            && $job->lightragInstance === 'team-meetings';
    });
});

it('startet die normale microsoft anmeldung für das onenote konto', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage-app-msgraph');

    Socialite::fake('microsoft');

    $this->actingAs($user)
        ->get(route('apps.msgraph.onenote-rag.connect'))
        ->assertRedirect()
        ->assertSessionHas(OnenoteDelegatedTokenService::CONNECT_SESSION_KEY, true);
});

it('lädt den intranet benutzer aus der datenbank und nicht aus ldap', function () {
    config([
        'auth.providers.users.model' => LdapUser::class,
        'auth.providers.users.database.model' => User::class,
        'intranet-app-msgraph.lightrag.api_key' => 'test-key',
        'intranet-app-msgraph.lightrag.instances.team-meetings' => [
            'label' => 'Team-Meetings',
            'url' => 'https://lightrag-onenote.example',
        ],
    ]);

    $user = User::factory()->create();

    $oneNote = Mockery::mock(MsGraphOneNoteServiceInterface::class);
    $oneNote->shouldReceive('getPageHtml')
        ->once()
        ->with(
            Mockery::on(fn (mixed $actor): bool => $actor instanceof User && $actor->is($user)),
            'https://graph.microsoft.com/v1.0/users/owner/onenote/pages/page-1/content',
        )
        ->andReturn('<p>Protokoll</p>');

    Http::fake([
        'https://lightrag-onenote.example/documents/text' => Http::response([
            'track_id' => 'insert_1',
        ]),
    ]);
    Event::fake([OnenotePageStatusChanged::class]);
    Bus::fake([SyncOnenoteLightRagTrack::class]);

    $job = new UploadOnenotePageToLightRag(
        actorId: $user->id,
        ownerType: 'user',
        ownerId: 'user-1',
        notebookId: 'nb-1',
        notebookName: 'Teammeetings',
        sectionId: 'sec-1',
        sectionName: 'Oktober',
        pageId: 'page-1',
        pageTitle: 'Brandschutz',
        contentUrl: 'https://graph.microsoft.com/v1.0/users/owner/onenote/pages/page-1/content',
        lightragInstance: 'team-meetings',
    );
    $job->handle($oneNote, app(LightRagOnenoteClient::class));

    $page = OnenoteRagPage::query()->where('page_id', 'page-1')->first();

    expect($page)->not->toBeNull()
        ->and($page->status)->toBe(OnenoteRagStatus::Processing)
        ->and($page->track_id)->toBe('insert_1')
        ->and($page->lightrag_instance)->toBe('team-meetings')
        ->and($page->error_message)->toBeNull();
});

it('reiht ein notizbuch und einen abschnitt zur verarbeitung ein', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage-app-msgraph');

    Bus::fake();

    $component = Livewire::actingAs($user)
        ->test(OneNoteRag::class)
        ->set('ownerId', 'user-1')
        ->set('notebookId', 'nb-wiki')
        ->set('lightragInstance', 'wiki')
        ->set('notebooks', [[
            'id' => 'nb-wiki',
            'name' => 'Wiki Schulung',
            'sectionsUrl' => 'https://graph.example/sections',
            'sectionGroupsUrl' => '',
            'webUrl' => 'https://contoso.sharepoint.com/teams/wiki',
            'ownerUserId' => 'user-1',
        ]])
        ->set('sections', [
            ['id' => 'sec-1', 'name' => 'Grundlagen', 'pagesUrl' => 'https://graph.example/sections/sec-1/pages'],
            ['id' => 'sec-2', 'name' => 'Vertiefung', 'pagesUrl' => 'https://graph.example/sections/sec-2/pages'],
        ])
        ->set('sectionId', 'sec-1')
        ->call('uploadNotebook');

    Bus::assertDispatched(QueueOnenotePagesForLightRag::class, function (QueueOnenotePagesForLightRag $job): bool {
        return $job->notebookId === 'nb-wiki'
            && $job->lightragInstance === 'wiki'
            && count($job->sections) === 2;
    });

    $component->call('uploadSection');

    Bus::assertDispatched(QueueOnenotePagesForLightRag::class, function (QueueOnenotePagesForLightRag $job): bool {
        return count($job->sections) === 1 && $job->sections[0]['id'] === 'sec-1';
    });
});

it('überspringt schon verarbeitete seiten beim sammeln eines abschnitts', function () {
    $user = User::factory()->create();

    OnenoteRagPage::query()->create([
        'owner_type' => 'user',
        'owner_id' => 'user-1',
        'notebook_id' => 'nb-1',
        'notebook_name' => 'Wiki Schulung',
        'section_id' => 'sec-1',
        'section_name' => 'Grundlagen',
        'page_id' => 'page-done',
        'page_title' => 'Alt',
        'lightrag_instance' => 'wiki',
        'status' => OnenoteRagStatus::Processed,
    ]);

    $oneNote = Mockery::mock(MsGraphOneNoteServiceInterface::class);
    $oneNote->shouldReceive('listPages')->once()->andReturn([
        'requestUrl' => 'https://graph.example/sections/sec-1/pages',
        'pages' => [
            ['id' => 'page-done', 'title' => 'Alt', 'contentUrl' => 'https://graph.example/pages/page-done/content'],
            ['id' => 'page-new', 'title' => 'Neu', 'contentUrl' => 'https://graph.example/pages/page-new/content'],
        ],
    ]);

    Bus::fake([UploadOnenotePageToLightRag::class]);

    (new QueueOnenotePagesForLightRag(
        actorId: $user->id,
        ownerType: 'user',
        ownerId: 'user-1',
        notebookId: 'nb-1',
        notebookName: 'Wiki Schulung',
        notebookWebUrl: '',
        fallbackOwnerId: 'user-1',
        lightragInstance: 'wiki',
        sections: [
            ['id' => 'sec-1', 'name' => 'Grundlagen', 'pagesUrl' => 'https://graph.example/sections/sec-1/pages'],
        ],
    ))->handle($oneNote, app(OnenotePageUploadQueue::class));

    Bus::assertDispatched(UploadOnenotePageToLightRag::class, fn (UploadOnenotePageToLightRag $job): bool => $job->pageId === 'page-new');
    Bus::assertNotDispatched(UploadOnenotePageToLightRag::class, fn (UploadOnenotePageToLightRag $job): bool => $job->pageId === 'page-done');

    expect(OnenoteRagPage::query()->where('page_id', 'page-new')->value('status'))->toBe('pending');
});
