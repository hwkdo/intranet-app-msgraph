<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Hwkdo\IntranetAppMsgraph\Support\OnenotePageStatusPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncOnenoteLightRagTrack implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $pageId,
    ) {}

    public function handle(LightRagOnenoteClient $lightRag): void
    {
        $record = OnenoteRagPage::query()->where('page_id', $this->pageId)->first();
        if ($record === null || $record->status !== OnenoteRagStatus::Processing) {
            return;
        }

        $trackId = $record->track_id;
        $instance = $record->lightrag_instance;
        if (! is_string($trackId) || $trackId === '' || ! is_string($instance) || $instance === '') {
            return;
        }

        try {
            $track = $lightRag->trackStatus($instance, $trackId);
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
            OnenotePageStatusPublisher::publish($record->refresh());

            return;
        }

        if ($track['status'] === 'failed') {
            $record->update([
                'status' => OnenoteRagStatus::Failed,
                'error_message' => $track['error_message'] ?? 'LightRAG-Verarbeitung fehlgeschlagen.',
            ]);
            OnenotePageStatusPublisher::publish($record->refresh());
        }
    }
}
