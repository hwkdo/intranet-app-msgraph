<?php

namespace Hwkdo\IntranetAppMsgraph\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppMsgraph\Models\AuslandseinsatzMembership;
use Hwkdo\IntranetAppMsgraph\Models\IntranetAppMsgraphSettings;
use Hwkdo\IntranetAppMsgraph\Services\GraphGroupService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Msgraph – Auslandszugriff')]
class Auslandszugriff extends Component
{
    public bool $showAddForm = false;

    public string $search = '';

    /** @var array<int, array{id: string, upn: string, displayName: string}> */
    public array $searchResults = [];

    /** @var array{id: string, upn: string, displayName: string}|null */
    public ?array $selectedUser = null;

    public ?int $anzahlTage = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->can('manage-app-msgraph') || $user->can('manage-app-msgraph-lehrgangsverwaltung'),
            403
        );
    }

    #[Computed]
    public function groupId(): string
    {
        $settings = IntranetAppMsgraphSettings::current();

        return $settings?->settings?->gruppenIdAuslandseinsatz ?? '7d01d307-f7be-4b9c-a108-f40ca4844f29';
    }

    /**
     * @return array<int, array{id: string, upn: string, displayName: string, addedAt: string, expiresAt: string|null, membershipId: int|null}>
     */
    #[Computed]
    public function members(): array
    {
        $groupId = $this->groupId;

        if (! $groupId) {
            return [];
        }

        $rawMembers = app(GraphGroupService::class)->getGroupMembers($groupId);

        $rawMembers = $this->filterByPermission($rawMembers);

        $trackingMap = AuslandseinsatzMembership::query()
            ->active()
            ->whereIn('upn', array_column($rawMembers, 'upn'))
            ->get()
            ->keyBy('upn');

        return array_map(function (array $member) use ($trackingMap) {
            /** @var AuslandseinsatzMembership|null $tracked */
            $tracked = $trackingMap->get($member['upn']);

            return [
                'id' => $member['id'],
                'upn' => $member['upn'],
                'displayName' => $member['displayName'],
                'addedAt' => $tracked?->created_at?->format('d.m.Y H:i') ?? '–',
                'expiresAt' => $tracked?->expires_at?->format('d.m.Y') ?? null,
                'membershipId' => $tracked?->id,
            ];
        }, $rawMembers);
    }

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->searchResults = [];

            return;
        }

        $userService = app(MsGraphUserServiceInterface::class);
        $result = $userService->getUsersPaginated(20, $this->search);

        $users = array_map(fn ($u) => [
            'id' => $u->getId() ?? '',
            'upn' => $u->getUserPrincipalName() ?? '',
            'displayName' => $u->getDisplayName() ?? '',
        ], $result['users']);

        $this->searchResults = $this->filterByPermission($users);
    }

    public function selectUser(string $upn, string $displayName, string $id): void
    {
        $this->selectedUser = [
            'id' => $id,
            'upn' => $upn,
            'displayName' => $displayName,
        ];
        $this->search = $displayName;
        $this->searchResults = [];
    }

    public function addUser(): void
    {
        if (! $this->selectedUser) {
            return;
        }

        $groupId = $this->groupId;

        $success = app(GraphGroupService::class)->addUserToGroup($groupId, $this->selectedUser['id']);

        if (! $success) {
            Flux::toast(heading: 'Fehler', text: 'Benutzer konnte nicht zur Gruppe hinzugefügt werden.', variant: 'danger');

            return;
        }

        AuslandseinsatzMembership::create([
            'upn' => $this->selectedUser['upn'],
            'user_display_name' => $this->selectedUser['displayName'],
            'added_by_upn' => Auth::user()->upn ?? Auth::user()->email,
            'expires_at' => $this->anzahlTage
                ? now()->addDays($this->anzahlTage)
                : null,
        ]);

        Flux::toast(heading: 'Erfolg', text: 'Benutzer wurde zur Gruppe hinzugefügt.', variant: 'success');

        $this->reset(['showAddForm', 'search', 'searchResults', 'selectedUser', 'anzahlTage']);
        unset($this->members);
    }

    public function removeUser(string $upn): void
    {
        $groupId = $this->groupId;

        $success = app(MsGraphUserServiceInterface::class)->removeUserFromGroup($upn, $groupId);

        if (! $success) {
            Flux::toast(heading: 'Fehler', text: 'Benutzer konnte nicht aus der Gruppe entfernt werden.', variant: 'danger');

            return;
        }

        AuslandseinsatzMembership::query()
            ->active()
            ->where('upn', $upn)
            ->delete();

        Flux::toast(heading: 'Erfolg', text: 'Benutzer wurde aus der Gruppe entfernt.', variant: 'success');

        unset($this->members);
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;

        if (! $this->showAddForm) {
            $this->reset(['search', 'searchResults', 'selectedUser', 'anzahlTage']);
        }
    }

    /**
     * Filtert Benutzer nach der Lehrgangsverwaltungs-Permission:
     * Benutzer ohne manage-app-msgraph sehen nur @hwkdoedu.onmicrosoft.com-Konten.
     *
     * @param  array<int, array{upn: string, ...}>  $users
     * @return array<int, array{upn: string, ...}>
     */
    private function filterByPermission(array $users): array
    {
        $currentUser = Auth::user();
        $isLehrgangsverwaltungOnly = $currentUser->can('manage-app-msgraph-lehrgangsverwaltung')
            && ! $currentUser->can('manage-app-msgraph');

        if (! $isLehrgangsverwaltungOnly) {
            return $users;
        }

        return array_values(array_filter($users, fn ($u) => str_contains($u['upn'], '@hwkdoedu.onmicrosoft.com')));
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.apps.msgraph.auslandszugriff');
    }
}
