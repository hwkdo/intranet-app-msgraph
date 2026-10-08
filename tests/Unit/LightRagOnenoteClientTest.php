<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\Exceptions\LightRagSourceConflictException;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Illuminate\Support\Facades\Http;

it('schickt seiten text an lightrag onenote und liest die track id', function () {
    config([
        'intranet-app-msgraph.lightrag.api_key' => 'test-key',
        'intranet-app-msgraph.lightrag.instances.team-meetings' => [
            'label' => 'Team-Meetings',
            'url' => 'https://lightrag-onenote.example',
        ],
    ]);

    Http::fake([
        'https://lightrag-onenote.example/documents/text' => Http::response([
            'status' => 'success',
            'track_id' => 'insert_1',
        ]),
    ]);

    $result = app(LightRagOnenoteClient::class)->insertText('team-meetings', 'Protokoll', 'onenote:page-1');

    expect($result['track_id'])->toBe('insert_1');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://lightrag-onenote.example/documents/text'
            && $request->hasHeader('X-API-Key', 'test-key')
            && $request['file_source'] === 'onenote:page-1'
            && $request['text'] === 'Protokoll';
    });
});

it('liest den verarbeitungsstatus einer seite', function () {
    config([
        'intranet-app-msgraph.lightrag.api_key' => 'test-key',
        'intranet-app-msgraph.lightrag.instances.team-meetings' => [
            'label' => 'Team-Meetings',
            'url' => 'https://lightrag-onenote.example',
        ],
    ]);

    Http::fake([
        'https://lightrag-onenote.example/documents/track_status/insert_1' => Http::response([
            'documents' => [[
                'id' => 'doc-1',
                'status' => 'processed',
                'error_msg' => null,
            ]],
        ]),
    ]);

    $status = app(LightRagOnenoteClient::class)->trackStatus('team-meetings', 'insert_1');

    expect($status['status'])->toBe('processed')
        ->and($status['doc_id'])->toBe('doc-1');
});

it('meldet einen bereits vorhandenen dateinamen als konflikt', function () {
    config([
        'intranet-app-msgraph.lightrag.api_key' => 'test-key',
        'intranet-app-msgraph.lightrag.instances.team-meetings' => [
            'label' => 'Team-Meetings',
            'url' => 'https://lightrag-onenote.example',
        ],
    ]);

    Http::fake([
        'https://lightrag-onenote.example/documents/text' => Http::response([
            'detail' => "Document storage already contains 'onenote:page-1'",
        ], 409),
    ]);

    expect(fn () => app(LightRagOnenoteClient::class)->insertText('team-meetings', 'Protokoll', 'onenote:page-1'))
        ->toThrow(LightRagSourceConflictException::class);
});

it('startet fehlgeschlagene dokumente neu', function () {
    config([
        'intranet-app-msgraph.lightrag.api_key' => 'test-key',
        'intranet-app-msgraph.lightrag.instances.team-meetings' => [
            'label' => 'Team-Meetings',
            'url' => 'https://lightrag-onenote.example',
        ],
    ]);

    Http::fake([
        'https://lightrag-onenote.example/documents/reprocess_failed' => Http::response([
            'status' => 'reprocessing_started',
            'message' => 'ok',
            'track_id' => '',
        ]),
    ]);

    app(LightRagOnenoteClient::class)->reprocessFailed('team-meetings');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://lightrag-onenote.example/documents/reprocess_failed'
            && $request->method() === 'POST';
    });
});
