<?php

namespace Hwkdo\IntranetAppMsgraph\Livewire\DashboardWidgets;

use Hwkdo\IntranetAppMsgraph\Support\UpcomingAzureAppSecretsList;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAppServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AzureAppSecretsExpiring extends Component
{
    public function itemLimit(): int
    {
        $value = Auth::user()?->settings->app->msgraph->dashboard['widgetItemCounts']['azure-app-secrets-expiring']
            ?? Auth::user()?->settings->dashboard->personalGrid?->widgetItemCounts['azure-app-secrets-expiring']
            ?? 5;

        return min(max((int) $value, 1), 30);
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('manage-app-msgraph'), 403);
    }

    /**
     * @return list<array{
     *     appDisplayName: string,
     *     appObjectId: string,
     *     appId: string,
     *     secretDisplayName: string,
     *     credentialType: string,
     *     endDateTime: \Carbon\Carbon,
     *     daysUntilExpiry: int
     * }>
     */
    #[Computed]
    public function items(): array
    {
        $apps = app(MsGraphAppServiceInterface::class)->getApplicationsWithSecrets();

        return UpcomingAzureAppSecretsList::nextExpiring($apps, $this->itemLimit());
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.apps.msgraph.widgets.azure-app-secrets-expiring');
    }
}
