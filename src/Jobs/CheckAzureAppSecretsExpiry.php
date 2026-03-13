<?php

namespace Hwkdo\IntranetAppMsgraph\Jobs;

use Hwkdo\IntranetAppMsgraph\Mail\AzureAppSecretExpiryWarning;
use Hwkdo\IntranetAppMsgraph\Models\IntranetAppMsgraphSettings;
use Hwkdo\IntranetAppMsgraph\Models\SecretExpiryNotification;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAppServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckAzureAppSecretsExpiry implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info('CheckAzureAppSecretsExpiry: Job gestartet');

        $settings = IntranetAppMsgraphSettings::current();
        if (! $settings?->settings) {
            Log::warning('CheckAzureAppSecretsExpiry: Keine Einstellungen gefunden (IntranetAppMsgraphSettings::current() fehlt oder settings leer)');

            return;
        }

        $thresholdDays = $settings->settings->secretExpiryWarningDays ?? 21;
        if ($thresholdDays <= 0) {
            Log::info('CheckAzureAppSecretsExpiry: Warnung deaktiviert (secretExpiryWarningDays = '.$thresholdDays.')');

            return;
        }

        $lastWarningDays = $settings->settings->secretExpiryLastWarningDays ?? 0;
        if ($lastWarningDays >= $thresholdDays || $lastWarningDays < 0) {
            $lastWarningDays = 0;
        }

        $notificationEmail = $settings->settings->secretExpiryNotificationEmail ?? '';
        $notificationEmail = trim($notificationEmail);
        if ($notificationEmail === '') {
            Log::warning('CheckAzureAppSecretsExpiry: Keine E-Mail-Adresse konfiguriert (secretExpiryNotificationEmail leer)');

            return;
        }

        $recipients = array_filter(array_map('trim', explode(',', $notificationEmail)));
        Log::info('CheckAzureAppSecretsExpiry: Prüfe Apps (Schwellwert = '.$thresholdDays.' Tage, Letzte Warnung = '.($lastWarningDays > 0 ? $lastWarningDays.' Tage' : 'aus').', Empfänger = '.implode(', ', $recipients).')');

        $appService = app(MsGraphAppServiceInterface::class);
        $apps = $appService->getApplicationsWithSecrets();
        $totalSecrets = 0;
        $emailsQueued = 0;

        foreach ($apps as $app) {
            $appObjectId = $app['id'] ?? null;
            $appId = $app['appId'] ?? '';
            $appDisplayName = $app['displayName'] ?? 'Unbekannt';
            $secrets = $app['secrets'] ?? [];

            foreach ($secrets as $secret) {
                $totalSecrets++;
                $keyId = $secret['keyId'] ?? null;
                if ($keyId === null || $keyId === '') {
                    continue;
                }

                $daysUntilExpiry = $secret['daysUntilExpiry'] ?? null;
                if ($daysUntilExpiry === null) {
                    continue;
                }

                if ($daysUntilExpiry < 0) {
                    continue;
                }

                if ($daysUntilExpiry > $thresholdDays) {
                    continue;
                }

                $notification = SecretExpiryNotification::findOrCreateForKey($keyId, $appObjectId);

                $sendFirstWarning = $notification->first_warning_sent_at === null;
                $sendLastWarning = $lastWarningDays > 0
                    && $daysUntilExpiry <= $lastWarningDays
                    && $notification->last_warning_sent_at === null;

                if ($sendFirstWarning) {
                    $this->sendWarningEmail($recipients, $appDisplayName, $appId, $secret, $daysUntilExpiry);
                    $notification->update(['first_warning_sent_at' => now()]);
                    $emailsQueued++;
                    Log::info('CheckAzureAppSecretsExpiry: Erste Ablaufwarnung für App "'.$appDisplayName.'", Secret "'.($secret['displayName'] ?? '–').'", läuft in '.$daysUntilExpiry.' Tagen ab');
                }

                if ($sendLastWarning) {
                    $this->sendWarningEmail($recipients, $appDisplayName, $appId, $secret, $daysUntilExpiry);
                    $notification->update(['last_warning_sent_at' => now()]);
                    $emailsQueued++;
                    Log::info('CheckAzureAppSecretsExpiry: Letzte Ablaufwarnung für App "'.$appDisplayName.'", Secret "'.($secret['displayName'] ?? '–').'", läuft in '.$daysUntilExpiry.' Tagen ab');
                }
            }
        }

        if ($emailsQueued > 0) {
            Log::info('CheckAzureAppSecretsExpiry: '.$emailsQueued.' E-Mail(s) in Queue gestellt.');
        } else {
            Log::info('CheckAzureAppSecretsExpiry: Keine neuen Warn-E-Mails nötig ('.$totalSecrets.' Secrets geprüft).');
        }
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $secret
     */
    private function sendWarningEmail(array $recipients, string $appDisplayName, string $appId, array $secret, int $daysUntilExpiry): void
    {
        $mailable = new AzureAppSecretExpiryWarning(
            appDisplayName: $appDisplayName,
            appId: $appId,
            secretDisplayName: $secret['displayName'] ?? '–',
            daysUntilExpiry: $daysUntilExpiry,
            endDateTime: $secret['endDateTime'] ?? null,
        );

        Mail::to($recipients)->queue($mailable);
    }
}
