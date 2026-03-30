<?php

namespace Hwkdo\IntranetAppMsgraph;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;
use Hwkdo\IntranetAppMsgraph\Dashboard\MsgraphDashboardWidgetProvider;
use Hwkdo\IntranetAppMsgraph\Data\AppSettings;
use Hwkdo\IntranetAppMsgraph\Data\UserSettings;
use Illuminate\Support\Collection;

class IntranetAppMsgraph implements IntranetAppInterface, ProvidesDashboardWidgetsInterface
{
    public static function app_name(): string
    {
        return 'Msgraph';
    }

    public static function app_icon(): string
    {
        return 'magnifying-glass';
    }

    public static function identifier(): string
    {
        return 'msgraph';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-msgraph.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-msgraph.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    public static function dashboardWidgetProviders(): array
    {
        return [
            MsgraphDashboardWidgetProvider::class,
        ];
    }
}
