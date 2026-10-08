<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

use Hwkdo\IntranetAppMsgraph\Enums\OnenoteRagStatus;
use Hwkdo\IntranetAppMsgraph\Jobs\UploadOnenotePageToLightRag;
use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;
use Hwkdo\IntranetAppMsgraph\Support\OnenotePageStatusPublisher;

class OnenotePageUploadQueue
{
    public function queue(
        int $actorId,
        string $ownerType,
        string $ownerId,
        string $notebookId,
        string $notebookName,
        string $sectionId,
        string $sectionName,
        string $pageId,
        string $pageTitle,
        string $contentUrl,
        string $lightragInstance,
    ): ?OnenoteRagPage {
        if ($contentUrl === '') {
            return null;
        }

        $existing = OnenoteRagPage::query()->where('page_id', $pageId)->first();
        if ($existing !== null && in_array($existing->status, [
            OnenoteRagStatus::Pending,
            OnenoteRagStatus::Processing,
            OnenoteRagStatus::Processed,
        ], true)) {
            return null;
        }

        $record = OnenoteRagPage::query()->updateOrCreate(
            ['page_id' => $pageId],
            [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'notebook_id' => $notebookId,
                'notebook_name' => $notebookName,
                'section_id' => $sectionId,
                'section_name' => $sectionName,
                'page_title' => $pageTitle,
                'lightrag_instance' => $lightragInstance,
                'status' => OnenoteRagStatus::Pending,
                'error_message' => null,
            ],
        );
        OnenotePageStatusPublisher::publish($record);

        UploadOnenotePageToLightRag::dispatch(
            actorId: $actorId,
            ownerType: $ownerType,
            ownerId: $ownerId,
            notebookId: $notebookId,
            notebookName: $notebookName,
            sectionId: $sectionId,
            sectionName: $sectionName,
            pageId: $pageId,
            pageTitle: $pageTitle,
            contentUrl: $contentUrl,
            lightragInstance: $lightragInstance,
        );

        return $record;
    }
}
