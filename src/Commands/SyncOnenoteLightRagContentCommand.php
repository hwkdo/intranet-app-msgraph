<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Commands;

use Hwkdo\IntranetAppMsgraph\Services\OnenoteLightRagReconciler;
use Illuminate\Console\Command;

class SyncOnenoteLightRagContentCommand extends Command
{
    protected $signature = 'onenote:sync-lightrag-content';

    protected $description = 'Gleich indexierte OneNote-Seiten mit LightRAG ab';

    public function handle(OnenoteLightRagReconciler $reconciler): int
    {
        if ($reconciler->run()) {
            $this->info('OneNote-RAG-Abgleich abgeschlossen.');

            return self::SUCCESS;
        }

        $this->error('OneNote-RAG-Abgleich fehlgeschlagen.');

        return self::FAILURE;
    }
}
