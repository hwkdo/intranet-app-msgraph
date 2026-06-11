<?php

namespace Hwkdo\IntranetAppMsgraph\Livewire;

use Flux\DateRange;
use Flux\Flux;
use Hwkdo\IntranetAppMsgraph\Enums\AuslandseinsatzMembershipStatus;
use Hwkdo\IntranetAppMsgraph\Models\AuslandseinsatzMembership;
use Hwkdo\IntranetAppMsgraph\Models\IntranetAppMsgraphSettings;
use Hwkdo\IntranetAppMsgraph\Services\GraphGroupService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    public ?DateRange $zeitraum = null;

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
     * @return array{managed: array<int, array<string, mixed>>, untracked: array<int, array<string, mixed>>}
     */
    #[Computed]
    public function entries(): array
    {
        $groupId = $this->groupId;

        $managed = AuslandseinsatzMembership::query()
            ->active()
            ->orderBy('starts_at')
            ->get()
            ->map(function (AuslandseinsatzMembership $membership): array {
                return [
                    'membershipId' => $membership->id,
                    'upn' => $membership->upn,
                    'displayName' => $membership->user_display_name,
                    'startsAt' => $membership->starts_at->format('d.m.Y'),
                    'endsAt' => $membership->ends_at->format('d.m.Y'),
                    'status' => $membership->status()->value,
                    'statusLabel' => $this->statusLabel($membership->status()),
                    'managed' => true,
                ];
            })
            ->all();

        $managed = $this->filterByPermission($managed);

        $managedUpns = array_column($managed, 'upn');

        $untracked = [];

        if ($groupId) {
            $rawMembers = app(GraphGroupService::class)->getGroupMembers($groupId);
            $rawMembers = $this->filterByPermission($rawMembers);

            foreach ($rawMembers as $member) {
                if (in_array($member['upn'], $managedUpns, true)) {
                    continue;
                }

                $untracked[] = [
                    'id' => $member['id'],
                    'upn' => $member['upn'],
                    'displayName' => $member['displayName'],
                    'startsAt' => '–',
                    'endsAt' => '–',
                    'status' => AuslandseinsatzMembershipStatus::NichtVerwaltet->value,
                    'statusLabel' => 'Nicht verwaltet',
                    'managed' => false,
                ];
            }
        }

        return [
            'managed' => array_values($managed),
            'untracked' => $untracked,
        ];
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

        $this->validate([
            'zeitraum' => ['required'],
        ], [
            'zeitraum.required' => 'Bitte einen Aufenthaltszeitraum wählen.',
        ]);

        if (! $this->zeitraum?->hasStart() || ! $this->zeitraum?->hasEnd()) {
            throw ValidationException::withMessages([
                'zeitraum' => 'Bitte Start- und Enddatum des Aufenthalts angeben.',
            ]);
        }

        $startsAt = $this->zeitraum->start()->startOfDay();
        $endsAt = $this->zeitraum->end()->startOfDay();

        if ($endsAt->lt($startsAt)) {
            throw ValidationException::withMessages([
                'zeitraum' => 'Das Enddatum muss am oder nach dem Startdatum liegen.',
            ]);
        }

        if ($startsAt->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'zeitraum' => 'Der Aufenthaltsbeginn darf nicht in der Vergangenheit liegen.',
            ]);
        }

        if ($startsAt->diffInDays($endsAt) > 365) {
            throw ValidationException::withMessages([
                'zeitraum' => 'Der Aufenthalt darf maximal 365 Tage dauern.',
            ]);
        }

        $membership = AuslandseinsatzMembership::create([
            'upn' => $this->selectedUser['upn'],
            'user_display_name' => $this->selectedUser['displayName'],
            'added_by_upn' => Auth::user()->upn ?? Auth::user()->email,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'azure_user_id' => $this->selectedUser['id'],
        ]);

        if ($membership->startsOnOrBeforeToday()) {
            $groupId = $this->groupId;
            $success = app(GraphGroupService::class)->addUserToGroup($groupId, $this->selectedUser['id']);

            if (! $success) {
                $membership->delete();
                Flux::toast(heading: 'Fehler', text: 'Benutzer konnte nicht zur Gruppe hinzugefügt werden.', variant: 'danger');

                return;
            }

            $membership->markAsActivated();
        }

        Flux::toast(
            heading: 'Erfolg',
            text: $membership->isActivated()
                ? 'Benutzer wurde zur Gruppe hinzugefügt.'
                : 'Aufenthalt geplant — Gruppenbeitritt erfolgt automatisch zum Startdatum.',
            variant: 'success'
        );

        $this->reset(['showAddForm', 'search', 'searchResults', 'selectedUser', 'zeitraum']);
        unset($this->entries);
    }

    public function removeUntrackedMember(string $upn): void
    {
        abort_unless($this->canModifyUpn($upn), 403);

        $groupId = $this->groupId;
        $success = app(MsGraphUserServiceInterface::class)->removeUserFromGroup($upn, $groupId);

        if (! $success) {
            Flux::toast(heading: 'Fehler', text: 'Benutzer konnte nicht aus der Gruppe entfernt werden.', variant: 'danger');

            return;
        }

        Flux::toast(heading: 'Erfolg', text: 'Benutzer wurde aus der Gruppe entfernt.', variant: 'success');

        unset($this->entries);
    }

    public function removeMembership(int $membershipId): void
    {
        $membership = AuslandseinsatzMembership::query()->active()->findOrFail($membershipId);

        abort_unless($this->canModifyUpn($membership->upn), 403);

        if ($membership->isActivated()) {
            $groupId = $this->groupId;
            $success = app(MsGraphUserServiceInterface::class)->removeUserFromGroup($membership->upn, $groupId);

            if (! $success) {
                Flux::toast(heading: 'Fehler', text: 'Benutzer konnte nicht aus der Gruppe entfernt werden.', variant: 'danger');

                return;
            }
        }

        $membership->markAsRemoved();

        Flux::toast(heading: 'Erfolg', text: 'Eintrag wurde entfernt.', variant: 'success');

        unset($this->entries);
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;

        if (! $this->showAddForm) {
            $this->reset(['search', 'searchResults', 'selectedUser', 'zeitraum']);
        }
    }

    /**
     * @param  array<int, array{upn: string, ...}>  $users
     * @return array<int, array{upn: string, ...}>
     */
    private function filterByPermission(array $users): array
    {
        return array_values(array_filter($users, fn ($u) => $this->canModifyUpn($u['upn'])));
    }

    private function canModifyUpn(string $upn): bool
    {
        $currentUser = Auth::user();
        $isLehrgangsverwaltungOnly = $currentUser->can('manage-app-msgraph-lehrgangsverwaltung')
            && ! $currentUser->can('manage-app-msgraph');

        if (! $isLehrgangsverwaltungOnly) {
            return true;
        }

        return str_contains($upn, '@hwkdoedu.onmicrosoft.com');
    }

    private function statusLabel(AuslandseinsatzMembershipStatus $status): string
    {
        return match ($status) {
            AuslandseinsatzMembershipStatus::Geplant => 'Geplant',
            AuslandseinsatzMembershipStatus::Aktiv => 'Aktiv',
            AuslandseinsatzMembershipStatus::Abgelaufen => 'Abgelaufen',
            AuslandseinsatzMembershipStatus::Entfernt => 'Entfernt',
            AuslandseinsatzMembershipStatus::NichtVerwaltet => 'Nicht verwaltet',
        };
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.apps.msgraph.auslandszugriff');
    }
}
