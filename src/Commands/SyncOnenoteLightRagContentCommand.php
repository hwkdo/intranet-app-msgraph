<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Commands;

use Hwkdo\IntranetAppBase\Services\BackgroundActivityOutput;
use Hwkdo\IntranetAppMsgraph\Services\OnenoteLightRagReconciler;
use Illuminate\Console\Command;

class SyncOnenoteLightRagContentCommand extends Command
{
    protected $signature = 'onenote:sync-lightrag-content';

    protected $description = 'Gleich indexierte OneNote-Seiten mit LightRAG ab';

    public function handle(OnenoteLightRagReconciler $reconciler): int
    {
        $report = $reconciler->run();
        $summary = $report->summary();
        $outputClass = BackgroundActivityOutput::class;

        if (class_exists($outputClass)) {
            app($outputClass)->remember($summary);
        }

        if ($report->successful()) {
            $this->info($summary);
        } else {
            $this->error($summary);
        }

        return $report->successful() ? self::SUCCESS : self::FAILURE;
    }
}
