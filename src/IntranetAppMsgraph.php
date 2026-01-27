<?php

namespace Hwkdo\IntranetAppMsgraph;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Illuminate\Support\Collection;

class IntranetAppMsgraph implements IntranetAppInterface
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
        return \Hwkdo\IntranetAppMsgraph\Data\UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return \Hwkdo\IntranetAppMsgraph\Data\AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }
}
