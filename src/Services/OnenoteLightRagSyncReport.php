<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

final class OnenoteLightRagSyncReport
{
    public int $unchanged = 0;

    public int $queuedUpdates = 0;

    public int $queuedNew = 0;

    public int $deleted = 0;

    public int $baselined = 0;

    public int $skipped = 0;

    public int $timestampsRepaired = 0;

    public int $graphBatches = 0;

    public bool $empty = false;

    public bool $started = false;

    /** @var list<string> */
    public array $failures = [];

    public function successful(): bool
    {
        return $this->failures === [];
    }

    public function summary(): string
    {
        $lines = [
            $this->successful()
                ? 'OneNote-RAG-Abgleich abgeschlossen.'
                : 'OneNote-RAG-Abgleich fehlgeschlagen.',
        ];

        if ($this->empty) {
            $lines[] = 'Keine indexierten OneNote-Seiten.';

            return implode("\n", $lines);
        }

        if ($this->started) {
            $lines[] = 'Seiten unverändert: '.$this->unchanged;
            $lines[] = 'Seiten zur Aktualisierung eingereiht: '.$this->queuedUpdates;
            $lines[] = 'Seiten neu eingereiht: '.$this->queuedNew;
            $lines[] = 'Seiten gelöscht: '.$this->deleted;

            if ($this->baselined > 0) {
                $lines[] = 'Seiten mit gespeichertem Zeitstempel: '.$this->baselined;
            }

            if ($this->timestampsRepaired > 0) {
                $lines[] = 'Zeitstempel korrigiert: '.$this->timestampsRepaired;
            }

            if ($this->skipped > 0) {
                $lines[] = 'Seiten übersprungen: '.$this->skipped;
            }

            if ($this->graphBatches > 0) {
                $lines[] = 'Graph-Batches: '.$this->graphBatches;
            }
        }

        foreach (array_values(array_unique($this->failures)) as $failure) {
            $lines[] = $failure;
        }

        return implode("\n", $lines);
    }
}
