<?php

namespace Hwkdo\IntranetAppMsgraph\Livewire;

use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAppServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Msgraph – Azure Apps')]
class AzureApps extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-app-msgraph'), 403);
    }

    /**
     * @return array<int, array{id: string, appId: string, displayName: string, secrets: array}>
     */
    #[Computed]
    public function apps(): array
    {
        $apps = app(MsGraphAppServiceInterface::class)->getApplicationsWithSecrets();

        if ($this->search === '') {
            return $apps;
        }

        $needle = mb_strtolower($this->search);

        return array_values(array_filter(
            $apps,
            fn (array $app) => str_contains(mb_strtolower($app['displayName']), $needle),
        ));
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.apps.msgraph.azure-apps');
    }
}
