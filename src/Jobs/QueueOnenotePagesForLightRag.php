<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Hwkdo\IntranetAppMsgraph\Services\OnenotePageUploadQueue;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Support\OnenoteGraphUrl;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class QueueOnenotePagesForLightRag implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @param  list<array{id: string, name: string, pagesUrl: string}>  $sections
     */
    public function __construct(
        public int $actorId,
        public string $ownerType,
        public string $ownerId,
        public string $notebookId,
        public string $notebookName,
        public string $notebookWebUrl,
        public string $fallbackOwnerId,
        public string $lightragInstance,
        public array $sections,
    ) {}

    public function handle(
        MsGraphOneNoteServiceInterface $oneNote,
        OnenotePageUploadQueue $queue,
    ): void {
        $actor = $this->intranetUser();

        foreach ($this->sections as $section) {
            $sectionId = $section['id'];
            $pagesUrl = OnenoteGraphUrl::sectionPagesUrl('', $sectionId, $section['pagesUrl']);
            if ($pagesUrl === '') {
                continue;
            }

            try {
                $loaded = $oneNote->listPages($actor, $pagesUrl, $this->notebookWebUrl, $this->fallbackOwnerId);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            foreach ($loaded['pages'] as $page) {
                $queue->queue(
                    actorId: $this->actorId,
                    ownerType: $this->ownerType,
                    ownerId: $this->ownerId,
                    notebookId: $this->notebookId,
                    notebookName: $this->notebookName,
                    sectionId: $sectionId,
                    sectionName: $section['name'],
                    pageId: $page['id'],
                    pageTitle: $page['title'],
                    contentUrl: $page['contentUrl'],
                    lightragInstance: $this->lightragInstance,
                );
            }
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
