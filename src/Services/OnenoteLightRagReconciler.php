<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Notifications\OnenoteRagSyncFailedNotification;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class OnenoteLightRagReconciler
{
    public function __construct(
        private MsGraphOneNoteServiceInterface $oneNote,
        private OnenoteDelegatedTokenInterface $tokens,
        private LightRagOnenoteClient $lightRag,
        private OnenotePageUploadQueue $queue,
    ) {}

    public function run(): bool
    {
        $pages = OnenoteRagPage::query()->orderBy('id')->get();
        if ($pages->isEmpty()) {
            return true;
        }

        try {
            $this->tokens->accessToken();
        } catch (Throwable $exception) {
            $this->notify('Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception, 'Token ungültig.'));

            return false;
        }

        try {
            $actor = $this->connectedActor();
        } catch (InvalidArgumentException $exception) {
            $this->notify($exception->getMessage());

            return false;
        }

        $failures = [];

        foreach ($pages->groupBy(fn (OnenoteRagPage $page): string => $page->owner_type.'|'.$page->owner_id.'|'.$page->notebook_id) as $notebookPages) {
            $sample = $notebookPages->first();
            if (! $sample instanceof OnenoteRagPage) {
                continue;
            }

            $instance = $this->instanceFor($notebookPages);
            if ($instance === null) {
                $failures[] = 'Notizbuch '.$sample->notebook_name.' hat keine LightRAG-Instanz.';

                continue;
            }

            try {
                $sections = $this->oneNote->listSections(
                    $actor,
                    $this->sectionsUrl($sample),
                    $this->sectionGroupsUrl($sample),
                );
            } catch (Throwable $exception) {
                if ($this->isAuthenticationFailure($exception)) {
                    $this->notify('Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception));

                    return false;
                }

                if ($this->isMissing($exception)) {
                    $failures = array_merge($failures, $this->removePages($notebookPages));

                    continue;
                }

                $failures[] = $sample->notebook_name.': '.GraphExceptionMessage::resolve($exception, 'Abschnitte konnten nicht gelesen werden.');

                continue;
            }

            $knownSectionIds = $notebookPages->pluck('section_id')->unique()->all();
            $remoteSections = collect($sections['sections'])->keyBy('id');

            foreach ($notebookPages->groupBy('section_id') as $sectionId => $sectionPages) {
                $section = $remoteSections->get($sectionId);
                if (! is_array($section)) {
                    $failures = array_merge($failures, $this->removePages($sectionPages));

                    continue;
                }

                try {
                    $listed = $this->oneNote->listPages($actor, (string) $section['pagesUrl'], '', $sample->owner_id);
                } catch (Throwable $exception) {
                    if ($this->isAuthenticationFailure($exception)) {
                        $this->notify('Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception));

                        return false;
                    }

                    $failures[] = ($section['name'] ?? $sectionId).': '.GraphExceptionMessage::resolve($exception, 'Seiten konnten nicht gelesen werden.');

                    continue;
                }

                $remotePages = collect($listed['pages'])->keyBy('id');
                foreach ($sectionPages as $record) {
                    $remote = $remotePages->get($record->page_id);
                    if (! is_array($remote)) {
                        $failures = array_merge($failures, $this->removePages(collect([$record])));

                        continue;
                    }

                    $this->refreshIfChanged($record, $remote, (string) ($section['name'] ?? $record->section_name));
                }

                foreach ($listed['pages'] as $remote) {
                    if ($sectionPages->contains('page_id', $remote['id'])) {
                        continue;
                    }

                    $this->enqueue($sample, (string) $sectionId, (string) ($section['name'] ?? ''), $remote, $instance);
                }
            }

            foreach ($sections['sections'] as $section) {
                if (in_array($section['id'], $knownSectionIds, true)) {
                    continue;
                }

                try {
                    $listed = $this->oneNote->listPages($actor, $section['pagesUrl'], '', $sample->owner_id);
                } catch (Throwable $exception) {
                    if ($this->isAuthenticationFailure($exception)) {
                        $this->notify('Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception));

                        return false;
                    }

                    $failures[] = $section['name'].': '.GraphExceptionMessage::resolve($exception, 'Seiten konnten nicht gelesen werden.');

                    continue;
                }

                foreach ($listed['pages'] as $remote) {
                    $this->enqueue($sample, $section['id'], $section['name'], $remote, $instance);
                }
            }
        }

        if ($failures !== []) {
            $this->notify(implode("\n", array_unique($failures)));

            return false;
        }

        return true;
    }

    /**
     * @param  array{id: string, title: string, contentUrl: string, lastModifiedAt?: ?string}  $remote
     */
    private function refreshIfChanged(OnenoteRagPage $record, array $remote, string $sectionName): void
    {
        if ($record->status === OnenoteRagStatus::Processing || $record->status === OnenoteRagStatus::Pending) {
            return;
        }

        $modified = $remote['lastModifiedAt'] ?? null;
        if (! is_string($modified) || $modified === '') {
            return;
        }

        if ($record->source_modified_at === null) {
            $record->update(['source_modified_at' => $modified]);

            return;
        }

        if ($record->source_modified_at->utc()->equalTo(\Illuminate\Support\Carbon::parse($modified)->utc())) {
            return;
        }

        $instance = $record->lightrag_instance;
        if (! is_string($instance) || $instance === '') {
            return;
        }

        $this->enqueue($record, $record->section_id, $sectionName, $remote, $instance, true);
    }

    /**
     * @param  array{id: string, title: string, contentUrl: string, lastModifiedAt?: ?string}  $remote
     */
    private function enqueue(
        OnenoteRagPage $sample,
        string $sectionId,
        string $sectionName,
        array $remote,
        string $instance,
        bool $refresh = false,
    ): void {
        $this->queue->queue(
            actorId: null,
            ownerType: $sample->owner_type,
            ownerId: $sample->owner_id,
            notebookId: $sample->notebook_id,
            notebookName: $sample->notebook_name,
            sectionId: $sectionId,
            sectionName: $sectionName,
            pageId: $remote['id'],
            pageTitle: $remote['title'],
            contentUrl: $remote['contentUrl'],
            lightragInstance: $instance,
            sourceModifiedAt: is_string($remote['lastModifiedAt'] ?? null) ? $remote['lastModifiedAt'] : null,
            refresh: $refresh,
        );
    }

    /**
     * @param  Collection<int, OnenoteRagPage>  $pages
     * @return list<string>
     */
    private function removePages(Collection $pages): array
    {
        $failures = [];

        foreach ($pages as $page) {
            $docId = $page->lightrag_doc_id;
            $instance = $page->lightrag_instance;
            try {
                if (is_string($docId) && $docId !== '' && is_string($instance) && $instance !== '') {
                    $this->lightRag->deleteDocument($instance, $docId);
                }
                $page->delete();
            } catch (Throwable $exception) {
                $failures[] = $page->page_title.': '.GraphExceptionMessage::resolve($exception, 'Die Seite konnte nicht aus LightRAG entfernt werden.');
            }
        }

        return $failures;
    }

    /**
     * @param  Collection<int, OnenoteRagPage>  $pages
     */
    private function instanceFor(Collection $pages): ?string
    {
        $instance = $pages->pluck('lightrag_instance')->first(fn (mixed $value): bool => is_string($value) && $value !== '');

        return is_string($instance) ? $instance : null;
    }

    private function sectionsUrl(OnenoteRagPage $page): string
    {
        return $this->notebookUrl($page).'/sections';
    }

    private function sectionGroupsUrl(OnenoteRagPage $page): string
    {
        return $this->notebookUrl($page).'/sectionGroups';
    }

    private function notebookUrl(OnenoteRagPage $page): string
    {
        $kind = $page->owner_type === 'group' ? 'groups' : 'users';

        return 'https://graph.microsoft.com/v1.0/'.$kind.'/'.rawurlencode($page->owner_id).'/onenote/notebooks/'.rawurlencode($page->notebook_id);
    }

    private function connectedActor(): Authenticatable
    {
        $upn = $this->tokens->connectedUpn();
        if (! is_string($upn) || $upn === '') {
            throw new InvalidArgumentException('Für OneNote ist noch kein Microsoft-Konto verbunden.');
        }

        return new GenericUser([
            'id' => $upn,
            'email' => $upn,
        ]);
    }

    private function notify(string $reason): void
    {
        $userClass = config('auth.providers.users.database.model')
            ?? config('auth.providers.users.model');

        if (! is_string($userClass) || ! class_exists($userClass) || ! is_subclass_of($userClass, Model::class)) {
            return;
        }

        $userClass::query()
            ->permission('manage-app-msgraph')
            ->where('active', true)
            ->orderBy('id')
            ->each(function (Model $user) use ($reason): void {
                if ($user instanceof Authenticatable && method_exists($user, 'notify')) {
                    $user->notify(new OnenoteRagSyncFailedNotification($reason));
                }
            });
    }

    private function isAuthenticationFailure(Throwable $exception): bool
    {
        $message = GraphExceptionMessage::resolve($exception, '');

        return str_contains($message, 'InvalidAuthenticationToken')
            || str_contains($message, 'Lifetime validation failed')
            || str_contains($message, 'neu verbunden')
            || str_contains($message, 'kein Microsoft-Konto');
    }

    private function isMissing(Throwable $exception): bool
    {
        $message = GraphExceptionMessage::resolve($exception, '');

        return str_contains($message, 'ItemNotFound');
    }
}
