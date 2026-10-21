<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use App\Models\User;
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

    public int $timeout = 1800;

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
        $sections = [];

        foreach ($this->sections as $section) {
            $pagesUrl = OnenoteGraphUrl::sectionPagesUrl('', $section['id'], $section['pagesUrl']);
            if ($pagesUrl === '') {
                continue;
            }

            $sections[$pagesUrl] = $section;
        }

        if ($sections === []) {
            return;
        }

        try {
            $batched = $oneNote->listPagesBatch($actor, array_keys($sections), $this->fallbackOwnerId);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($sections as $pagesUrl => $section) {
            $failure = $batched['failures'][$pagesUrl] ?? null;
            if (is_string($failure)) {
                report(new \RuntimeException($section['name'].': '.$failure));

                continue;
            }

            foreach ($batched['pages'][$pagesUrl]['pages'] ?? [] as $page) {
                $queue->queue(
                    actorId: $this->actorId,
                    ownerType: $this->ownerType,
                    ownerId: $this->ownerId,
                    notebookId: $this->notebookId,
                    notebookName: $this->notebookName,
                    sectionId: $section['id'],
                    sectionName: $section['name'],
                    pageId: $page['id'],
                    pageTitle: $page['title'],
                    contentUrl: $page['contentUrl'],
                    lightragInstance: $this->lightragInstance,
                    sourceModifiedAt: is_string($page['lastModifiedAt'] ?? null) ? $page['lastModifiedAt'] : null,
                );
            }
        }
    }

    private function intranetUser(): Authenticatable
    {
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
