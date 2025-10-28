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

    ) {}
}
