<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppMsgraph\IntranetAppMsgraph;
use Hwkdo\IntranetAppMsgraph\Jobs\CheckAzureAppSecretsExpiry;
use Hwkdo\IntranetAppMsgraph\Jobs\ProcessAuslandseinsatzMemberships;
use Hwkdo\IntranetAppMsgraph\Jobs\QueueOnenotePagesForLightRag;
use Hwkdo\IntranetAppMsgraph\Jobs\SyncOnenoteLightRagTrack;
use Hwkdo\IntranetAppMsgraph\Jobs\UploadOnenotePageToLightRag;
use Hwkdo\IntranetAppMsgraph\Mail\AzureAppSecretExpiryWarning;

final class MsgraphBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppMsgraph::identifier();
        $name = IntranetAppMsgraph::app_name();
        $all = BackgroundActivityCadence::cases();

        return [
            BackgroundActivityDefinition::scheduledJob(
                key: 'msgraph.auslandseinsatz-memberships',
                label: 'Auslandseinsatz-Gruppen',
                appIdentifier: $id,
                appName: $name,
                job: ProcessAuslandseinsatzMemberships::class,
                frequency: BackgroundActivityFrequency::everyHours(4),
                description: 'Aktiviert oder entfernt Azure-Gruppenmitgliedschaften für Auslandseinsätze.',
                allowedCadences: $all,
                minEvery: 1,
                maxEvery: 24,
                sort: 10,
            ),
            BackgroundActivityDefinition::scheduledJob(
                key: 'msgraph.secret-expiry',
                label: 'Azure-Secret-Ablauf',
                appIdentifier: $id,
                appName: $name,
                job: CheckAzureAppSecretsExpiry::class,
                frequency: BackgroundActivityFrequency::dailyAt('06:00'),
                description: 'Warnt vor ablaufenden Azure-App-Secrets.',
                allowedCadences: $all,
                sort: 20,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'msgraph.onenote-lightrag-content',
                label: 'OneNote LightRAG-Abgleich',
                appIdentifier: $id,
                appName: $name,
                command: 'onenote:sync-lightrag-content',
                frequency: BackgroundActivityFrequency::everyHours(1),
                description: 'Übernimmt neue und geänderte OneNote-Seiten und entfernt gelöschte Seiten aus LightRAG.',
                allowedCadences: $all,
                minEvery: 1,
                maxEvery: 24,
                withoutOverlappingMinutes: 120,
                sort: 35,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'msgraph.onenote-lightrag-status',
                label: 'OneNote LightRAG-Status',
                appIdentifier: $id,
                appName: $name,
                command: 'onenote:sync-lightrag-status',
                frequency: BackgroundActivityFrequency::everyMinutes(1),
                description: 'Synchronisiert den LightRAG-Status von OneNote-Seiten.',
                allowedCadences: $all,
                minEvery: 1,
                maxEvery: 60,
                withoutOverlappingMinutes: 1440,
                sort: 30,
            ),
            BackgroundActivityDefinition::mail(
                key: 'msgraph.mail-secret-expiry',
                label: 'Mail: Secret-Ablauf',
                appIdentifier: $id,
                appName: $name,
                mailable: AzureAppSecretExpiryWarning::class,
                sort: 40,
            ),
            BackgroundActivityDefinition::queued(key: 'msgraph.onenote-lightrag-upload', label: 'OneNote-Seite an LightRAG', appIdentifier: $id, appName: $name, job: UploadOnenotePageToLightRag::class, description: 'Überträgt eine OneNote-Seite an LightRAG.', sort: 50),
            BackgroundActivityDefinition::queued(key: 'msgraph.onenote-lightrag-track', label: 'OneNote LightRAG-Track', appIdentifier: $id, appName: $name, job: SyncOnenoteLightRagTrack::class, description: 'Aktualisiert den LightRAG-Track einer OneNote-Seite.', sort: 60),
            BackgroundActivityDefinition::queued(key: 'msgraph.onenote-lightrag-queue', label: 'OneNote-Seiten einreihen', appIdentifier: $id, appName: $name, job: QueueOnenotePagesForLightRag::class, description: 'Stellt OneNote-Seiten für den LightRAG-Upload in die Queue.', sort: 70),
        ];
    }
}
