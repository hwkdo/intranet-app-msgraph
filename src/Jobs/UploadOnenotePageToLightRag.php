<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use App\Models\User;
use Hwkdo\IntranetAppBase\Contracts\DocumentParseConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Exceptions\LightRagSourceConflictException;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Hwkdo\IntranetAppMsgraph\Support\OnenotePageStatusPublisher;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Hwkdo\MsGraphLaravel\Support\OnenoteGraphPacer;
use Hwkdo\MsGraphLaravel\Support\OnenoteGraphUrl;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class UploadOnenotePageToLightRag implements ShouldQueue
{
    use Queueable;

    public int $tries = 1000;

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new RateLimited('onenote-graph-content'))->releaseAfter(20)];
    }

    public function __construct(
        public ?int $actorId,
        public string $ownerType,
        public string $ownerId,
        public string $notebookId,
        public string $notebookName,
        public string $sectionId,
        public string $sectionName,
        public string $pageId,
        public string $pageTitle,
        public string $contentUrl,
        public string $lightragInstance,
        public ?string $sourceModifiedAt = null,
        public bool $refresh = false,
    ) {}

    public function handle(
        MsGraphOneNoteServiceInterface $oneNote,
        LightRagOnenoteClient $lightRag,
        IntranetAiGatewayInterface $gateway,
    ): void {
        $record = OnenoteRagPage::query()->updateOrCreate(
            ['page_id' => $this->pageId],
            [
                'owner_type' => $this->ownerType,
                'owner_id' => $this->ownerId,
                'notebook_id' => $this->notebookId,
                'notebook_name' => $this->notebookName,
                'section_id' => $this->sectionId,
                'section_name' => $this->sectionName,
                'page_title' => $this->pageTitle,
                'lightrag_instance' => $this->lightragInstance,
                'status' => OnenoteRagStatus::Processing,
                'error_message' => null,
            ],
        );
        OnenotePageStatusPublisher::publish($record);

        try {
            $actor = $this->intranetUser();
            $html = $oneNote->getPageHtml($actor, OnenoteGraphUrl::pageContentUrl('', $this->pageId, $this->contentUrl));

            if (trim(strip_tags($html)) === '') {
                throw new \RuntimeException('Die OneNote-Seite enthält keinen Text.');
            }

            $text = $this->parsedPage($gateway, $html);
            $hash = hash('sha256', $text);
            $fileSource = 'onenote:'.$this->pageId;

            if ($this->refresh && is_string($record->content_hash) && hash_equals($record->content_hash, $hash)) {
                $record->update([
                    'status' => OnenoteRagStatus::Processed,
                    'source_modified_at' => $this->sourceModifiedAt,
                    'error_message' => null,
                ]);
                OnenotePageStatusPublisher::publish($record->refresh());

                return;
            }

            if ($this->refresh && is_string($record->lightrag_doc_id) && $record->lightrag_doc_id !== '') {
                $lightRag->deleteDocument($this->lightragInstance, $record->lightrag_doc_id);
                $record->update(['lightrag_doc_id' => null]);
            }

            try {
                $trackId = $lightRag->insertText($this->lightragInstance, $text, $fileSource)['track_id'];
            } catch (LightRagSourceConflictException) {
                $lightRag->reprocessFailed($this->lightragInstance);
                $trackId = $record->track_id;
                if (! is_string($trackId) || $trackId === '') {
                    throw new \RuntimeException('Die Seite liegt schon in LightRAG, hat aber keine Verarbeitungsnummer.');
                }
            }

            $record->update([
                'track_id' => $trackId,
                'content_hash' => $hash,
                'source_modified_at' => $this->sourceModifiedAt ?? $record->source_modified_at,
                'status' => OnenoteRagStatus::Processing,
                'error_message' => null,
            ]);
            OnenotePageStatusPublisher::publish($record->refresh());
        } catch (Throwable $exception) {
            if (app(OnenoteGraphPacer::class)->isRateLimit($exception)) {
                throw $exception;
            }

            report($exception);
            $record->update([
                'status' => OnenoteRagStatus::Failed,
                'error_message' => GraphExceptionMessage::resolve($exception, 'Die Seite konnte nicht nach LightRAG übernommen werden.'),
            ]);
            OnenotePageStatusPublisher::publish($record->refresh());
        }
    }

    public function failed(Throwable $exception): void
    {
        $record = OnenoteRagPage::query()->where('page_id', $this->pageId)->first();
        if ($record === null || $record->status !== OnenoteRagStatus::Processing) {
            return;
        }

        $record->update([
            'status' => OnenoteRagStatus::Failed,
            'error_message' => GraphExceptionMessage::resolve($exception, 'Die Seite konnte nicht nach LightRAG übernommen werden.'),
        ]);
        OnenotePageStatusPublisher::publish($record);
    }

    private function parsedPage(IntranetAiGatewayInterface $gateway, string $html): string
    {
        $parsed = $this->preparePageText($gateway, $html);

        return trim("Notizbuch: {$this->notebookName}\nAbschnitt: {$this->sectionName}\nSeite: {$this->pageTitle}\n\n".$parsed);
    }

    /**
     * OneNote liefert HTML. Vision/pdftotext/HWK Admin brauchen PDF/Bilder —
     * nur LlamaParse darf die Seite noch einmal aufbereiten.
     */
    private function preparePageText(IntranetAiGatewayInterface $gateway, string $html): string
    {
        $engine = app(DocumentParseConfigResolverInterface::class)->resolve('msgraph')->engine;
        if (! $engine->acceptsTextPayload()) {
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                throw new \RuntimeException('Die OneNote-Seite enthält keinen Text.');
            }

            return $text;
        }

        $path = tempnam(sys_get_temp_dir(), 'onenote-rag-');
        if ($path === false) {
            throw new \RuntimeException('Temporäre OneNote-Datei konnte nicht angelegt werden.');
        }

        $htmlPath = $path.'.html';
        rename($path, $htmlPath);

        try {
            file_put_contents($htmlPath, $html);

            return $gateway->parseAppDocument($htmlPath, 'msgraph');
        } finally {
            if (is_file($htmlPath)) {
                @unlink($htmlPath);
            }
        }
    }

    private function intranetUser(): Authenticatable
    {
        if ($this->actorId === null) {
            $upn = app(OnenoteDelegatedTokenInterface::class)->connectedUpn();
            if (! is_string($upn) || $upn === '') {
                throw new \InvalidArgumentException('Für OneNote ist noch kein Microsoft-Konto verbunden.');
            }

            return new GenericUser([
                'id' => $upn,
                'email' => $upn,
            ]);
        }

        $userClass = config('auth.providers.users.database.model')
            ?? config('auth.providers.users.model');

        if (! is_string($userClass) || ! class_exists($userClass) || ! is_subclass_of($userClass, Model::class)) {
            $userClass = User::class;
        }

        $user = $userClass::query()->findOrFail($this->actorId);
        if (! $user instanceof Authenticatable) {
            throw new \RuntimeException('Der Intranet-Benutzer konnte nicht geladen werden.');
        }

        return $user;
    }
}
