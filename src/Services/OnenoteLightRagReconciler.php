<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Notifications\OnenoteRagSyncFailedNotification;
use Hwkdo\IntranetAppMsgraph\Support\OnenoteSourceModifiedAt;
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

    public function run(): OnenoteLightRagSyncReport
    {
        $report = new OnenoteLightRagSyncReport;
        $pages = OnenoteRagPage::query()->orderBy('id')->get();
        if ($pages->isEmpty()) {
            $report->empty = true;

            return $report;
        }

        try {
            $this->tokens->accessToken();
        } catch (Throwable $exception) {
            $report->failures[] = 'Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception, 'Token ungültig.');
            $this->notify($report->failures[0]);

            return $report;
        }

        try {
            $actor = $this->connectedActor();
        } catch (InvalidArgumentException $exception) {
            $report->failures[] = $exception->getMessage();
            $this->notify($exception->getMessage());

            return $report;
        }

        $report->started = true;

        foreach ($pages->groupBy(fn (OnenoteRagPage $page): string => $page->owner_type.'|'.$page->owner_id.'|'.$page->notebook_id) as $notebookPages) {
            $sample = $notebookPages->first();
            if (! $sample instanceof OnenoteRagPage) {
                continue;
            }

            $instance = $this->instanceFor($notebookPages);
            if ($instance === null) {
                $report->failures[] = 'Notizbuch '.$sample->notebook_name.' hat keine LightRAG-Instanz.';

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
                    $report->failures[] = 'Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception);
                    $this->notify($report->failures[array_key_last($report->failures)]);

                    return $report;
                }

                if ($this->isMissing($exception)) {
                    $this->removePages($notebookPages, $report);

                    continue;
                }

                $report->failures[] = $sample->notebook_name.': '.GraphExceptionMessage::resolve($exception, 'Abschnitte konnten nicht gelesen werden.');

                continue;
            }

            $knownSectionIds = $notebookPages->pluck('section_id')->unique()->all();
            $remoteSections = collect($sections['sections'])->keyBy('id');
            $work = [];

            foreach ($notebookPages->groupBy('section_id') as $sectionId => $sectionPages) {
                $section = $remoteSections->get($sectionId);
                if (! is_array($section)) {
                    $this->removePages($sectionPages, $report);

                    continue;
                }

                $work[] = [
                    'id' => (string) $sectionId,
                    'name' => (string) ($section['name'] ?? $sectionPages->first()?->section_name ?? $sectionId),
                    'pagesUrl' => (string) $section['pagesUrl'],
                    'records' => $sectionPages,
                ];
            }

            foreach ($sections['sections'] as $section) {
                if (in_array($section['id'], $knownSectionIds, true)) {
                    continue;
                }

                $work[] = [
                    'id' => (string) $section['id'],
                    'name' => (string) $section['name'],
                    'pagesUrl' => (string) $section['pagesUrl'],
                    'records' => collect(),
                ];
            }

            if ($work === []) {
                continue;
            }

            try {
                $batched = $this->oneNote->listPagesBatch(
                    $actor,
                    array_values(array_unique(array_map(
                        fn (array $item): string => $item['pagesUrl'],
                        $work,
                    ))),
                    $sample->owner_id,
                );
            } catch (Throwable $exception) {
                if ($this->isAuthenticationFailure($exception)) {
                    $report->failures[] = 'Das OneNote-Konto ist nicht nutzbar. '.GraphExceptionMessage::resolve($exception);
                    $this->notify($report->failures[array_key_last($report->failures)]);

                    return $report;
                }

                $report->failures[] = $sample->notebook_name.': '.GraphExceptionMessage::resolve($exception, 'Seiten konnten nicht gelesen werden.');

                continue;
            }

            $report->graphBatches += (int) ($batched['batches'] ?? 0);

            foreach ($work as $item) {
                $failure = $batched['failures'][$item['pagesUrl']] ?? null;
                if (is_string($failure)) {
                    if ($this->isAuthenticationFailure(new \RuntimeException($failure))) {
                        $report->failures[] = 'Das OneNote-Konto ist nicht nutzbar. '.$failure;
                        $this->notify($report->failures[array_key_last($report->failures)]);

                        return $report;
                    }

                    $report->failures[] = $item['name'].': '.$failure;

                    continue;
                }

                $listed = $batched['pages'][$item['pagesUrl']] ?? null;
                if (! is_array($listed)) {
                    $report->failures[] = $item['name'].': Seiten konnten nicht gelesen werden.';

                    continue;
                }

                $remotePages = collect($listed['pages'])->keyBy('id');
                foreach ($item['records'] as $record) {
                    $remote = $remotePages->get($record->page_id);
                    if (! is_array($remote)) {
                        $this->removePages(collect([$record]), $report);

                        continue;
                    }

                    $this->refreshIfChanged($report, $record, $remote, $item['name']);
                }

                foreach ($listed['pages'] as $remote) {
                    if ($item['records']->contains('page_id', $remote['id'])) {
                        continue;
                    }

                    $this->enqueue($report, $sample, $item['id'], $item['name'], $remote, $instance);
                }
            }
        }

        if ($report->failures !== []) {
            $this->notify(implode("\n", array_unique($report->failures)));
        }

        return $report;
    }

    /**
     * @param  array{id: string, title: string, contentUrl: string, lastModifiedAt?: ?string}  $remote
     */
    private function refreshIfChanged(OnenoteLightRagSyncReport $report, OnenoteRagPage $record, array $remote, string $sectionName): void
    {
        if ($record->status === OnenoteRagStatus::Processing || $record->status === OnenoteRagStatus::Pending) {
            $report->skipped++;

            return;
        }

        $modified = $remote['lastModifiedAt'] ?? null;
        if (! is_string($modified) || $modified === '') {
            return;
        }

        if ($record->source_modified_at === null) {
            $record->update(['source_modified_at' => $modified]);
            $report->baselined++;

            return;
        }

        if (OnenoteSourceModifiedAt::same($record->source_modified_at, $modified)) {
            if (OnenoteSourceModifiedAt::needsRepair($record->source_modified_at, $modified)) {
                $record->update(['source_modified_at' => $modified]);
                $report->timestampsRepaired++;
            }

            $report->unchanged++;

            return;
        }

        $instance = $record->lightrag_instance;
        if (! is_string($instance) || $instance === '') {
            return;
        }

        $this->enqueue($report, $record, $record->section_id, $sectionName, $remote, $instance, true);
    }

    /**
     * @param  array{id: string, title: string, contentUrl: string, lastModifiedAt?: ?string}  $remote
     */
    private function enqueue(
        OnenoteLightRagSyncReport $report,
        OnenoteRagPage $sample,
        string $sectionId,
        string $sectionName,
        array $remote,
        string $instance,
        bool $refresh = false,
    ): void {
        $queued = $this->queue->queue(
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

        if ($queued === null) {
            $report->skipped++;

            return;
        }

        if ($refresh) {
            $report->queuedUpdates++;

            return;
        }

        $report->queuedNew++;
    }

    /**
     * @param  Collection<int, OnenoteRagPage>  $pages
     */
    private function removePages(Collection $pages, OnenoteLightRagSyncReport $report): void
    {
        foreach ($pages as $page) {
            $docId = $page->lightrag_doc_id;
            $instance = $page->lightrag_instance;
            try {
                if (is_string($docId) && $docId !== '' && is_string($instance) && $instance !== '') {
                    $this->lightRag->deleteDocument($instance, $docId);
                }
                $page->delete();
                $report->deleted++;
            } catch (Throwable $exception) {
                $report->failures[] = $page->page_title.': '.GraphExceptionMessage::resolve($exception, 'Die Seite konnte nicht aus LightRAG entfernt werden.');
            }
        }
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
