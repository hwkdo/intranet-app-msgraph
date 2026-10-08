<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Commands;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Jobs\SyncOnenoteLightRagTrack;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Services\LightRagOnenoteClient;
use Illuminate\Console\Command;
use Throwable;

class SyncOnenoteLightRagStatusCommand extends Command
{
    protected $signature = 'onenote:sync-lightrag-status';

    protected $description = 'Schreibt den aktuellen LightRAG-Status der offenen OneNote-Seiten in die Datenbank';

    public function handle(LightRagOnenoteClient $lightRag): int
    {
        $written = 0;
        $open = 0;

        OnenoteRagPage::query()
            ->where('status', OnenoteRagStatus::Processing)
            ->whereNotNull('track_id')
            ->orderBy('id')
            ->each(function (OnenoteRagPage $page) use ($lightRag, &$written, &$open): void {
                try {
                    (new SyncOnenoteLightRagTrack($page->page_id))->handle($lightRag);
                } catch (Throwable $exception) {
                    $this->error($page->page_title.': '.$exception->getMessage());
                    $open++;

                    return;
                }

                $page->refresh();
                if ($page->status === OnenoteRagStatus::Processing) {
                    $open++;

                    return;
                }

                $written++;
            });

        $this->info($written.' Seiten aktualisiert, '.$open.' noch in Arbeit.');

        return self::SUCCESS;
    }
}
