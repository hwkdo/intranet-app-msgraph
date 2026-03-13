<?php

namespace Hwkdo\IntranetAppMsgraph\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AzureAppSecretExpiryWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $appDisplayName,
        public string $appId,
        public string $secretDisplayName,
        public int $daysUntilExpiry,
        public ?CarbonInterface $endDateTime = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysUntilExpiry < 0
            ? "Azure App {$this->appDisplayName} – Secret abgelaufen"
            : ($this->daysUntilExpiry === 1
                ? "Azure App {$this->appDisplayName} läuft in 1 Tag ab"
                : "Azure App {$this->appDisplayName} läuft in {$this->daysUntilExpiry} Tagen ab");

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-msgraph::emails.azure-app-secret-expiry-warning',
        );
    }
}
