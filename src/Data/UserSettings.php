<?php

namespace Hwkdo\IntranetAppMsgraph\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\Attributes\HiddenFromSettings;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;
use Hwkdo\IntranetAppMsgraph\Enums\UsersPerPageEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[Description('Anzahl der Entra User pro Seite')]
        #[WithCast(EnumCast::class)]
        public UsersPerPageEnum $defaultUsersPerPage = UsersPerPageEnum::TwentyFive,

        #[Description('Persönliches Dashboard-Layout (Widgets, Positionen, Größen)')]
        #[HiddenFromSettings]
        public array $dashboard = [
            'version' => 1,
            'enabledWidgets' => [],
            'layout' => [],
            'widgetItemCounts' => [],
        ],
    ) {}
}
