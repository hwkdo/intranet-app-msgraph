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
    public bool $showExpired = true;
    public string $sortField = 'end_date';
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-app-msgraph'), 403);
    }

    /**
     * @return array<int, array{
     *     appId: string,
     *     appInternalId: string,
     *     appDisplayName: string,
     *     credentialType: string,
     *     keyId: string,
     *     displayName: string,
     *     endDateTime: mixed,
     *     daysUntilExpiry: int|null,
     *     status: string
     * }>
     */
    #[Computed]
    public function rows(): array
    {
        $apps = app(MsGraphAppServiceInterface::class)->getApplicationsWithSecrets();

        $needle = mb_strtolower($this->search);
        $rows = [];

        foreach ($apps as $app) {
            $appDisplayName = $app['displayName'] ?? '';
            $appMatchesSearch = $needle === '' || str_contains(mb_strtolower($appDisplayName), $needle);

            foreach (($app['secrets'] ?? []) as $secret) {
                $daysUntilExpiry = $secret['daysUntilExpiry'] ?? null;
                $displayName = (string) ($secret['displayName'] ?? '');
                $secretMatchesSearch = $needle === '' || str_contains(mb_strtolower($displayName), $needle);

                if (! $appMatchesSearch && ! $secretMatchesSearch) {
                    continue;
                }

                if (! $this->showExpired && is_int($daysUntilExpiry) && $daysUntilExpiry < 0) {
                    continue;
                }

                $rows[] = [
                    'appInternalId' => (string) ($app['id'] ?? ''),
                    'appId' => (string) ($app['appId'] ?? ''),
                    'appDisplayName' => $appDisplayName,
                    'credentialType' => (string) ($secret['credentialType'] ?? 'secret'),
                    'keyId' => (string) ($secret['keyId'] ?? ''),
                    'displayName' => $displayName,
                    'endDateTime' => $secret['endDateTime'] ?? null,
                    'daysUntilExpiry' => is_int($daysUntilExpiry) ? $daysUntilExpiry : null,
                    'status' => (string) ($secret['status'] ?? 'unknown'),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b));

        return $rows;
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['end_date', 'remaining_days'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    private function compareRows(array $leftRow, array $rightRow): int
    {
        $leftValue = $this->sortField === 'remaining_days'
            ? $this->remainingDaysValue($leftRow)
            : $this->endDateValue($leftRow);
        $rightValue = $this->sortField === 'remaining_days'
            ? $this->remainingDaysValue($rightRow)
            : $this->endDateValue($rightRow);

        if ($leftValue === null && $rightValue === null) {
            return 0;
        }

        if ($leftValue === null) {
            return 1;
        }

        if ($rightValue === null) {
            return -1;
        }

        $comparison = $leftValue <=> $rightValue;

        return $this->sortDirection === 'asc' ? $comparison : -$comparison;
    }

    private function endDateValue(array $row): ?int
    {
        $endDateTime = $row['endDateTime'] ?? null;

        return $endDateTime?->getTimestamp();
    }

    private function remainingDaysValue(array $row): ?int
    {
        $daysUntilExpiry = $row['daysUntilExpiry'] ?? null;

        return is_int($daysUntilExpiry) ? $daysUntilExpiry : null;
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.apps.msgraph.azure-apps');
    }
}
