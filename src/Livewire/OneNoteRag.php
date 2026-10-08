<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Livewire;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Jobs\QueueOnenotePagesForLightRag;
use Hwkdo\IntranetAppMsgraph\Services\OnenoteDelegatedTokenService;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Hwkdo\IntranetAppMsgraph\Services\OnenotePageUploadQueue;
use Hwkdo\IntranetAppMsgraph\Support\OnenoteLightRagTarget;
use Hwkdo\IntranetAppMsgraph\Support\OnenotePageStatusPublisher;
use Hwkdo\MsGraphLaravel\Exceptions\OneNoteGraphRequestException;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphGroupServiceInterface;
use Hwkdo\MsGraphLaravel\Support\OnenoteGraphUrl;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Msgraph – OneNote-RAG')]
class OneNoteRag extends Component
{
    public string $sourceType = 'user';

    public string $ownerQuery = '';

    public ?string $ownerId = null;

    public string $ownerLabel = '';

    /** @var list<array{id: string, name: string, sectionsUrl: string, sectionGroupsUrl: string, webUrl: string, ownerUserId: string}> */
    public array $notebooks = [];

    public ?string $notebookId = null;

    /** @var list<array{id: string, name: string, pagesUrl: string}> */
    public array $sections = [];

    public ?string $sectionId = null;

    public string $lightragInstance = '';

    /**
     * @var list<array{id: string, title: string, contentUrl: string, status: string, statusLabel: string, error: ?string, canUpload: bool}>
     */
    public array $pages = [];

    public ?string $errorMessage = null;

    public ?string $infoMessage = null;

    public string $requestUrl = '';

    public string $sectionsRequestUrl = '';

    public ?string $onenoteAccountUpn = null;

    public function mount(OnenoteDelegatedTokenService $tokens): void
    {
        abort_unless(Auth::user()?->can('manage-app-msgraph'), 403);
        $this->onenoteAccountUpn = $tokens->connectedUpn();
        $connectError = session('onenote-connect-error');
        if (is_string($connectError) && $connectError !== '') {
            $this->errorMessage = $connectError;
        }
    }

    public function updatedSourceType(): void
    {
        $this->reset('ownerId', 'ownerLabel', 'notebooks', 'notebookId', 'sections', 'sectionId', 'pages', 'errorMessage', 'infoMessage', 'requestUrl', 'sectionsRequestUrl', 'lightragInstance');
    }

    public function loadNotebooks(MsGraphOneNoteServiceInterface $oneNote): void
    {
        $this->errorMessage = null;
        $this->infoMessage = null;
        $this->notebooks = [];
        $this->sections = [];
        $this->pages = [];
        $this->notebookId = null;
        $this->sectionId = null;
        $this->requestUrl = '';
        $this->sectionsRequestUrl = '';

        try {
            $actor = Auth::user();
            if ($actor === null) {
                throw new \InvalidArgumentException('Nicht angemeldet.');
            }

            $this->ownerId = $this->resolveOwnerId();
            $this->notebooks = $oneNote->listNotebooks($actor, $this->sourceType, (string) $this->ownerId);
        } catch (Throwable $exception) {
            $this->ownerId = null;
            $this->errorMessage = GraphExceptionMessage::resolve($exception, 'OneNote-Bücher konnten nicht geladen werden.');
        }
    }

    public function selectNotebook(string $notebookId, MsGraphOneNoteServiceInterface $oneNote): void
    {
        $this->errorMessage = null;
        $this->notebookId = $notebookId;
        $this->sectionId = null;
        $this->pages = [];

        $actor = Auth::user();
        if ($this->ownerId === null || $actor === null) {
            return;
        }

        $notebook = collect($this->notebooks)->firstWhere('id', $notebookId);
        $notebookName = is_array($notebook) ? (string) ($notebook['name'] ?? '') : '';
        $this->lightragInstance = OnenoteLightRagTarget::forNotebook($notebookId, $notebookName) ?? '';
        $sectionsUrl = is_array($notebook) ? (string) ($notebook['sectionsUrl'] ?? '') : '';
        if ($sectionsUrl === '') {
            $this->errorMessage = 'Für dieses Notizbuch hat Graph keine Abschnittsadresse geliefert.';

            return;
        }

        $this->requestUrl = $sectionsUrl;

        try {
            $loaded = $oneNote->listSections(
                $actor,
                $sectionsUrl,
                (string) ($notebook['sectionGroupsUrl'] ?? ''),
                (string) ($notebook['webUrl'] ?? ''),
            );
            $this->requestUrl = $loaded['requestUrl'];
            $this->sectionsRequestUrl = $loaded['requestUrl'];
            $this->sections = $loaded['sections'];
        } catch (OneNoteGraphRequestException $exception) {
            $this->sections = [];
            $this->requestUrl = $exception->requestUrl;
            $this->errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            $this->sections = [];
            $this->errorMessage = GraphExceptionMessage::resolve($exception, 'Abschnitte konnten nicht geladen werden.');
        }
    }

    public function selectSection(string $sectionId, MsGraphOneNoteServiceInterface $oneNote, LightRagOnenoteClient $lightRag): void
    {
        $this->errorMessage = null;
        $this->sectionId = $sectionId;

        $actor = Auth::user();
        if ($this->ownerId === null || $actor === null) {
            return;
        }

        $section = collect($this->sections)->firstWhere('id', $sectionId);
        $storedPagesUrl = is_array($section) ? (string) ($section['pagesUrl'] ?? '') : '';
        $pagesUrl = OnenoteGraphUrl::sectionPagesUrl($this->sectionsRequestUrl, $sectionId, $storedPagesUrl);
        if ($pagesUrl === '') {
            $this->requestUrl = '';
            $this->errorMessage = 'Für diesen Abschnitt hat Graph keine Seitenadresse geliefert.';

            return;
        }

        $this->requestUrl = $pagesUrl;

        try {
            $notebook = collect($this->notebooks)->firstWhere('id', $this->notebookId);
            $notebookOwnerId = is_array($notebook) ? (string) ($notebook['ownerUserId'] ?? '') : '';
            $loaded = $oneNote->listPages(
                $actor,
                $pagesUrl,
                is_array($notebook) ? (string) ($notebook['webUrl'] ?? '') : '',
                $notebookOwnerId !== '' ? $notebookOwnerId : ($this->sourceType === 'user' ? (string) $this->ownerId : ''),
            );
            $this->requestUrl = $loaded['requestUrl'];
            $pages = $loaded['pages'];
            $this->pages = $this->pagesWithStatus($pages, $lightRag);
        } catch (OneNoteGraphRequestException $exception) {
            $this->pages = [];
            $this->requestUrl = $exception->requestUrl;
            $this->errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            $this->pages = [];
            $this->errorMessage = GraphExceptionMessage::resolve($exception, 'Seiten konnten nicht geladen werden.');
        }
    }

    public function uploadPage(string $pageId, OnenotePageUploadQueue $queue): void
    {
        $page = collect($this->pages)->firstWhere('id', $pageId);
        $contentUrl = is_array($page) ? (string) ($page['contentUrl'] ?? '') : '';
        if (! is_array($page) || ! ($page['canUpload'] ?? false) || $contentUrl === '') {
            return;
        }

        if ($this->ownerId === null || $this->notebookId === null || $this->sectionId === null) {
            return;
        }

        $sectionName = collect($this->sections)->firstWhere('id', $this->sectionId)['name'] ?? '';
        if (! $this->ensureLightRagInstance()) {
            return;
        }

        $record = $queue->queue(
            actorId: (int) Auth::id(),
            ownerType: $this->sourceType,
            ownerId: $this->ownerId,
            notebookId: $this->notebookId,
            notebookName: $this->notebookName(),
            sectionId: $this->sectionId,
            sectionName: $sectionName,
            pageId: $pageId,
            pageTitle: $page['title'],
            contentUrl: $contentUrl,
            lightragInstance: $this->lightragInstance,
        );

        if ($record === null) {
            return;
        }

        $this->pages = collect($this->pages)->map(function (array $row) use ($pageId, $record): array {
            if ($row['id'] !== $pageId) {
                return $row;
            }

            return $this->pageRowWithStatus($row, $record->status, $record->error_message);
        })->all();
    }

    public function uploadSection(): void
    {
        $section = collect($this->sections)->firstWhere('id', $this->sectionId);
        if (! is_array($section)) {
            return;
        }

        $this->queueSections([$section], 'Der Abschnitt wird eingereiht. Seiten, die schon in LightRAG sind, bleiben unverändert.');
    }

    public function uploadNotebook(): void
    {
        if ($this->sections === []) {
            return;
        }

        $this->queueSections($this->sections, 'Das Notizbuch wird eingereiht. Seiten, die schon in LightRAG sind, bleiben unverändert.');
    }

    /**
     * @param  array{pageId?: mixed, status?: mixed, error?: mixed}  $payload
     */
    public function applyOnenotePageStatus(array $payload): void
    {
        $pageId = $payload['pageId'] ?? null;
        if (! is_string($pageId) || $pageId === '') {
            return;
        }

        $status = OnenoteRagStatus::tryFrom((string) ($payload['status'] ?? ''));
        $error = $payload['error'] ?? null;

        $this->pages = collect($this->pages)->map(function (array $row) use ($pageId, $status, $error): array {
            if ($row['id'] !== $pageId) {
                return $row;
            }

            return $this->pageRowWithStatus($row, $status, is_string($error) ? $error : null);
        })->all();
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        if (! Auth::user()?->can('manage-app-msgraph')) {
            return [];
        }

        return [
            'echo-private:msgraph-onenote-rag,.onenote.page.status' => 'applyOnenotePageStatus',
        ];
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.apps.msgraph.onenote-rag');
    }

    /**
     * @param  list<array{id: string, name: string, pagesUrl: string}>  $sections
     */
    private function queueSections(array $sections, string $message): void
    {
        if ($this->ownerId === null || $this->notebookId === null || Auth::id() === null) {
            return;
        }

        if (! $this->ensureLightRagInstance()) {
            return;
        }

        $notebook = collect($this->notebooks)->firstWhere('id', $this->notebookId);
        $notebookOwnerId = is_array($notebook) ? (string) ($notebook['ownerUserId'] ?? '') : '';

        QueueOnenotePagesForLightRag::dispatch(
            actorId: (int) Auth::id(),
            ownerType: $this->sourceType,
            ownerId: $this->ownerId,
            notebookId: $this->notebookId,
            notebookName: $this->notebookName(),
            notebookWebUrl: is_array($notebook) ? (string) ($notebook['webUrl'] ?? '') : '',
            fallbackOwnerId: $notebookOwnerId !== '' ? $notebookOwnerId : ($this->sourceType === 'user' ? $this->ownerId : ''),
            lightragInstance: $this->lightragInstance,
            sections: array_values($sections),
        );

        $sectionIds = array_column($sections, 'id');
        if ($this->sectionId !== null && in_array($this->sectionId, $sectionIds, true)) {
            $this->pages = collect($this->pages)->map(function (array $row): array {
                if (! ($row['canUpload'] ?? false)) {
                    return $row;
                }

                return $this->pageRowWithStatus($row, OnenoteRagStatus::Pending, null);
            })->all();
        }

        $this->errorMessage = null;
        $this->infoMessage = $message;
    }

    private function ensureLightRagInstance(): bool
    {
        $instance = OnenoteLightRagTarget::known($this->lightragInstance)
            ? $this->lightragInstance
            : (OnenoteLightRagTarget::forNotebook((string) $this->notebookId, $this->notebookName()) ?? '');

        if ($instance === '') {
            $this->infoMessage = null;
            $this->errorMessage = 'Für dieses Notizbuch ist keine LightRAG-Instanz gewählt.';

            return false;
        }

        $this->lightragInstance = $instance;

        return true;
    }

    private function notebookName(): string
    {
        $name = collect($this->notebooks)->firstWhere('id', $this->notebookId)['name'] ?? '';

        return is_string($name) ? $name : '';
    }

    private function resolveOwnerId(): string
    {
        $query = trim($this->ownerQuery);
        if ($query === '') {
            throw new \InvalidArgumentException($this->sourceType === 'group'
                ? 'Gruppenname fehlt.'
                : 'Benutzer-UPN fehlt.');
        }

        if ($this->sourceType === 'group') {
            $groupId = app(MsGraphGroupServiceInterface::class)->getGroupIdByName($query);
            if ($groupId === null) {
                throw new \InvalidArgumentException('Gruppe wurde nicht gefunden.');
            }

            $this->ownerLabel = $query;

            return $groupId;
        }

        $user = app(MsGraphUserServiceInterface::class)->getUserByUpn($query);
        $userId = $user?->getId();
        if (! is_string($userId) || $userId === '') {
            throw new \InvalidArgumentException('Benutzer wurde nicht gefunden.');
        }

        $this->ownerLabel = $query;

        return $userId;
    }

    /**
     * @param  list<array{id: string, title: string, contentUrl: string}>  $pages
     * @return list<array{id: string, title: string, contentUrl: string, status: string, statusLabel: string, error: ?string, canUpload: bool}>
     */
    private function pagesWithStatus(array $pages, LightRagOnenoteClient $lightRag): array
    {
        $ids = array_column($pages, 'id');
        $records = OnenoteRagPage::query()->whereIn('page_id', $ids)->get()->keyBy('page_id');

        foreach ($records as $record) {
            if ($record->status === OnenoteRagStatus::Processing && is_string($record->track_id) && $record->track_id !== '') {
                $this->refreshTrack($record, $lightRag);
            }
        }

        return array_map(function (array $page) use ($records): array {
            /** @var OnenoteRagPage|null $record */
            $record = $records->get($page['id']);
            $status = $record?->status;
            $canUpload = $status === null || $status === OnenoteRagStatus::Failed;

            return [
                'id' => $page['id'],
                'title' => $page['title'],
                'contentUrl' => $page['contentUrl'],
                'status' => $status?->value ?? '',
                'statusLabel' => $this->statusLabel($status),
                'error' => $record?->error_message,
                'canUpload' => $canUpload,
            ];
        }, $pages);
    }

    private function refreshTrack(OnenoteRagPage $record, LightRagOnenoteClient $lightRag): void
    {
        try {
            $instance = $record->lightrag_instance;
            if (! is_string($instance) || $instance === '') {
                return;
            }

            $track = $lightRag->trackStatus($instance, (string) $record->track_id);
        } catch (Throwable) {
            return;
        }

        if ($track['status'] === 'processed') {
            $record->update([
                'status' => OnenoteRagStatus::Processed,
                'lightrag_doc_id' => $track['doc_id'],
                'error_message' => null,
                'indexed_at' => now(),
            ]);
            $record->refresh();
            OnenotePageStatusPublisher::publish($record);

            return;
        }

        if ($track['status'] === 'failed') {
            $record->update([
                'status' => OnenoteRagStatus::Failed,
                'error_message' => $track['error_message'] ?? 'LightRAG-Verarbeitung fehlgeschlagen.',
            ]);
            $record->refresh();
            OnenotePageStatusPublisher::publish($record);
        }
    }

    private function statusLabel(?OnenoteRagStatus $status): string
    {
        return match ($status) {
            OnenoteRagStatus::Pending => 'wartet',
            OnenoteRagStatus::Processing => 'wird verarbeitet',
            OnenoteRagStatus::Processed => 'in LightRAG',
            OnenoteRagStatus::Failed => 'fehlgeschlagen',
            default => 'nicht hochgeladen',
        };
    }

    /**
     * @param  array{id: string, title: string, contentUrl: string, status: string, statusLabel: string, error: ?string, canUpload: bool}  $row
     * @return array{id: string, title: string, contentUrl: string, status: string, statusLabel: string, error: ?string, canUpload: bool}
     */
    private function pageRowWithStatus(array $row, ?OnenoteRagStatus $status, ?string $error): array
    {
        $row['status'] = $status?->value ?? OnenoteRagStatus::Pending->value;
        $row['statusLabel'] = $this->statusLabel($status);
        $row['canUpload'] = $status === null || $status === OnenoteRagStatus::Failed;
        $row['error'] = $error;

        return $row;
    }
}
