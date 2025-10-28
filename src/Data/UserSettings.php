<?php

namespace Hwkdo\IntranetAppMsgraph\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;
use Hwkdo\IntranetAppMsgraph\Enums\ViewModeEnum;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[Description('Standard-Anzeigemodus für die App')]
        public ViewModeEnum $defaultViewMode = ViewModeEnum::Grid,

        #[Description('Benachrichtigungen aktiviert')]
        public bool $notificationsEnabled = true,
    ) {}
}
