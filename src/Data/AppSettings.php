<?php

namespace Hwkdo\IntranetAppMsgraph\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
{
    public function __construct(
        #[Description('Aktiviert die Beispiel-Funktionalität')]
        public bool $enableExampleFeature = true,

        #[Description('Maximale Anzahl von Elementen pro Seite')]
        public int $maxItemsPerPage = 25,

        #[Description('Gruppen-ID der Auslandseinsatz-Gruppe in Azure Entra')]
        public string $gruppenIdAuslandseinsatz = '7d01d307-f7be-4b9c-a108-f40ca4844f29',

        #[Description('E-Mail-Warnung senden, wenn ein Azure-App-Secret in maximal so vielen Tagen abläuft (0 = deaktiviert)')]
        public int $secretExpiryWarningDays = 21,

        #[Description('Zusätzliche letzte E-Mail, wenn Secret nur noch so viele Tage gültig ist (muss kleiner als SecretExpiryWarningDays sein, 0 = deaktiviert)')]
        public int $secretExpiryLastWarningDays = 3,

        #[Description('E-Mail-Adresse(n) für Azure-App-Secret-Ablaufwarnungen (kommagetrennt)')]
        public string $secretExpiryNotificationEmail = '',
    ) {}
}
