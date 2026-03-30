<?php

namespace Hwkdo\IntranetAppMsgraph\Dashboard;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;

class MsgraphDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    public static function widgets(): array
    {
        return [
            new DashboardWidgetDefinition(
                key: 'azure-app-secrets-expiring',
                title: 'Nächste Azure-App-Abläufe',
                description: 'Die nächsten ablaufenden App-Secrets und Zertifikate (Microsoft Entra)',
                component: 'apps.msgraph.widgets.azure-app-secrets-expiring',
                permission: 'manage-app-msgraph',
                defaultW: 6,
                defaultH: 5,
                minW: 4,
                minH: 4,
                defaultEnabled: true,
            ),
        ];
    }
}
