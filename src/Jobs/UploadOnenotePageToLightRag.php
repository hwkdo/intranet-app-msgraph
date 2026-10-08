<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Exceptions\LightRagSourceConflictException;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Hwkdo\IntranetAppMsgraph\Support\OnenoteHtmlText;
use Hwkdo\IntranetAppMsgraph\Support\OnenotePageStatusPublisher;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Hwkdo\MsGraphLaravel\Support\OnenoteGraphUrl;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class UploadOnenotePageToLightRag implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $actorId,
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
    ) {}

    public function handle(
        MsGraphOneNoteServiceInterface $oneNote,
        LightRagOnenoteClient $lightRag,
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

            $text = OnenoteHtmlText::toPlainText($html, $this->notebookName, $this->sectionName, $this->pageTitle);
            $fileSource = 'onenote:'.$this->pageId;

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
                'status' => OnenoteRagStatus::Processing,
                'error_message' => null,
            ]);
            OnenotePageStatusPublisher::publish($record->refresh());
        } catch (Throwable $exception) {
            report($exception);
            $record->update([
                'status' => OnenoteRagStatus::Failed,
                'error_message' => GraphExceptionMessage::resolve($exception, 'Die Seite konnte nicht nach LightRAG übernommen werden.'),
            ]);
            OnenotePageStatusPublisher::publish($record->refresh());
        }
    }

    private function intranetUser(): Authenticatable
    {
        $userClass = config('auth.providers.users.database.model')
            ?? config('auth.providers.users.model');

        if (! is_string($userClass) || ! class_exists($userClass) || ! is_subclass_of($userClass, Model::class)) {
            $userClass = \App\Models\User::class;
        }

        $user = $userClass::query()->findOrFail($this->actorId);
        if (! $user instanceof Authenticatable) {
            throw new \RuntimeException('Der Intranet-Benutzer konnte nicht geladen werden.');
        }

        return $user;
    }
}
