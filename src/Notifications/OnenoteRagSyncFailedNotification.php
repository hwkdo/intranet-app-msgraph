<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppMsgraph\IntranetAppMsgraph;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class OnenoteRagSyncFailedNotification extends IntranetNotification
{
    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'msgraph.onenote_rag_sync_failed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('OneNote-RAG Sync fehlgeschlagen')
            ->line('Der Abgleich der OneNote-Seiten mit LightRAG ist fehlgeschlagen.')
            ->line($this->reason)
            ->action('OneNote-RAG öffnen', route('apps.msgraph.onenote-rag.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'OneNote-RAG Sync fehlgeschlagen',
            body: $this->reason,
            url: route('apps.msgraph.onenote-rag.index'),
            appIdentifier: IntranetAppMsgraph::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('OneNote-RAG Sync fehlgeschlagen')
            ->body($this->reason)
            ->data(['url' => route('apps.msgraph.onenote-rag.index')]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'OneNote-RAG Sync fehlgeschlagen: '.$this->reason,
            'topic' => 'OneNote-RAG',
            'url' => route('apps.msgraph.onenote-rag.index'),
        ];
    }
}
