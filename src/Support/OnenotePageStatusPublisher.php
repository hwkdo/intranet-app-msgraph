<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Support;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Events\OnenotePageStatusChanged;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Throwable;

class OnenotePageStatusPublisher
{
    public static function publish(OnenoteRagPage $page): void
    {
        $status = $page->status;

        try {
            OnenotePageStatusChanged::dispatch(
                $page->page_id,
                $status instanceof OnenoteRagStatus ? $status->value : (string) $status,
                $page->error_message,
            );
        } catch (Throwable) {
            // Der gespeicherte Status bleibt gültig, auch wenn Reverb gerade nicht erreichbar ist.
        }
    }
}
