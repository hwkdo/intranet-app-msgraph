<?php

namespace Hwkdo\IntranetAppMsgraph\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;
use Hwkdo\IntranetAppMsgraph\Enums\UsersPerPageEnum;
use Hwkdo\IntranetAppMsgraph\Enums\ViewModeEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[Description('Standard-Anzeigemodus für die App')]
        public ViewModeEnum $defaultViewMode = ViewModeEnum::Grid,

        #[Description('Benachrichtigungen aktiviert')]
        public bool $notificationsEnabled = true,

        #[Description('Anzahl der Entra User pro Seite')]
        #[WithCast(EnumCast::class)]
        public UsersPerPageEnum $defaultUsersPerPage = UsersPerPageEnum::TwentyFive,
    ) {}
}
