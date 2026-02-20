<?php

use Flux\Flux;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAuthenticationServiceInterface;
use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{mount, state, title, updated, computed};

title('Msgraph - Entra-User');

state([
    'search' => '',
    'users' => [],
    'nextLink' => null,
    'currentPage' => 1,
    'usersPerPage' => 25,
    'selectedUser' => null,
    'loading' => false,
]);

// Auth methods are derived for the currently selected user to keep state minimal
$authMethods = computed(function () {
	if (! $this->selectedUser) {
		return [];
	}

	return app(MsGraphAuthenticationServiceInterface::class)->getMethods($this->selectedUser['upn']);
});

$getAuthMethod = function (string $type, string $methodId) {
	if (! $this->selectedUser) {
		return [];
	}

	return app(MsGraphAuthenticationServiceInterface::class)
		->getMethod($this->selectedUser['upn'], $type, $methodId);
};

$deleteAuthMethod = function (string $typeWithoutHash, string $methodId) {
	if (! $this->selectedUser) {
		return;
	}

	$type = '#' . $typeWithoutHash; // restore leading '#'
	app(MsGraphAuthenticationServiceInterface::class)
		->deleteMethod($this->selectedUser['upn'], $type, $methodId);

	unset($this->authMethods); // refresh computed on next render

	Flux::toast(
		heading: 'Erfolg',
		text: 'Authentifizierungsmethode wurde gelöscht.',
		variant: 'success'
	);
};

mount(function () {
    // Lade usersPerPage aus den User-Settings
    $user = Auth::user();
    $msgraphSettings = $user->settings->app->msgraph;

    if ($msgraphSettings && isset($msgraphSettings->defaultUsersPerPage)) {
        $this->usersPerPage = $msgraphSettings->defaultUsersPerPage->value;
    }

    // Lade erste Seite
    $this->loadUsers();
});

updated(['search' => function () {
    // Reset pagination when search changes
    $this->currentPage = 1;
    $this->loadUsers();
}]);

$loadUsers = function () {
    $this->loading = true;

    $userService = app(MsGraphUserServiceInterface::class);

    $result = $userService->getUsersPaginated(
        $this->usersPerPage,
        $this->search ?: null
    );

    $currentUser = Auth::user();
    $isLehrgangsverwaltungUser = $currentUser->can('manage-app-msgraph-lehrgangsverwaltung') 
        && !$currentUser->can('manage-app-msgraph');

    // Konvertiere MS Graph User-Objekte in Arrays für Livewire
    $users = array_map(function ($user) {
        return [
            'id' => $user->getId(),
            'userPrincipalName' => $user->getUserPrincipalName(),
            'displayName' => $user->getDisplayName(),
            'mail' => $user->getMail(),
            'jobTitle' => $user->getJobTitle(),
        ];
    }, $result['users']);

    // Filtere Benutzer basierend auf Berechtigungen
    if ($isLehrgangsverwaltungUser) {
        $users = array_filter($users, function ($user) {
            return str_contains($user['userPrincipalName'], '@hwkdoedu.onmicrosoft.com');
        });
    }

    $this->users = array_values($users);
    $this->nextLink = $result['nextLink'];
    $this->loading = false;
};

$nextPage = function () {
    if (! $this->nextLink) {
        return;
    }

    $this->loading = true;
    $userService = app(MsGraphUserServiceInterface::class);

    $currentUser = Auth::user();
    $isLehrgangsverwaltungUser = $currentUser->can('manage-app-msgraph-lehrgangsverwaltung') 
        && !$currentUser->can('manage-app-msgraph');

    // Lade die nächste Seite mit dem nextLink
    $result = $userService->getUsersPaginated(
        $this->usersPerPage,
        $this->search ?: null,
        $this->nextLink
    );

    // Konvertiere und füge neue User zur bestehenden Liste hinzu
    $newUsers = array_map(function ($user) {
        return [
            'id' => $user->getId(),
            'userPrincipalName' => $user->getUserPrincipalName(),
            'displayName' => $user->getDisplayName(),
            'mail' => $user->getMail(),
            'jobTitle' => $user->getJobTitle(),
        ];
    }, $result['users']);

    // Filtere Benutzer basierend auf Berechtigungen
    if ($isLehrgangsverwaltungUser) {
        $newUsers = array_filter($newUsers, function ($user) {
            return str_contains($user['userPrincipalName'], '@hwkdoedu.onmicrosoft.com');
        });
    }

    $this->users = array_merge($this->users, array_values($newUsers));
    $this->nextLink = $result['nextLink'];
    $this->currentPage++;
    $this->loading = false;
};

$resetSearch = function () {
    $this->search = '';
    $this->currentPage = 1;
    $this->loadUsers();
};

$showUserDetails = function ($upn) {
    $userService = app(MsGraphUserServiceInterface::class);
    $details = $userService->getUserDetails($upn);
    
    // Konvertiere das Objekt in ein Array für Livewire
    if ($details && $details->user) {
        $accountEnabled = $details->user->getAccountEnabled();
        
        $this->selectedUser = [
            'upn' => $details->user->getUserPrincipalName(),
            'displayName' => $details->user->getDisplayName(),
            'mail' => $details->user->getMail(),
            'jobTitle' => $details->user->getJobTitle(),
            'department' => $details->user->getDepartment(),
            'mobilePhone' => $details->user->getMobilePhone(),
            'officeLocation' => $details->user->getOfficeLocation(),
            'accountEnabled' => $accountEnabled === null ? true : (bool) $accountEnabled,
            'businessPhones' => $details->user->getBusinessPhones(),
            'manager' => $details->manager ? $details->manager->getDisplayName() : null,
            'groups' => $details->groups ? array_map(fn($g) => [
                'displayName' => $g->getDisplayName() ?? $g->getId(),
                'id' => $g->getId()
            ], $details->groups) : [],
            'teams' => $details->teams ? array_map(fn($t) => [
                'displayName' => $t->getDisplayName() ?? $t->getId(),
                'id' => $t->getId()
            ], $details->teams) : [],
        ];
    }
    
    Flux::modal('user-details-modal')->show();
};

$activateUser = function () {
    if (! $this->selectedUser || $this->selectedUser['accountEnabled']) {
        return;
    }

    $userService = app(MsGraphUserServiceInterface::class);
    $success = $userService->activateUser($this->selectedUser['upn']);

    if ($success) {
        $this->selectedUser['accountEnabled'] = true;
        Flux::toast(
            heading: 'Benutzer aktiviert',
            text: 'Der Benutzer wurde erfolgreich aktiviert.',
            variant: 'success'
        );
    } else {
        Flux::toast(
            heading: 'Fehler',
            text: 'Der Benutzer konnte nicht aktiviert werden.',
            variant: 'danger'
        );
    }
};

$resetPassword = function () {
    if (! $this->selectedUser) {
        return;
    }

    $hwkAdminService = app(HwkAdminService::class);
    $currentUser = Auth::user();
    $upn = $this->selectedUser['upn'];
    $mailEmpfaenger = $currentUser->email;

    $success = $hwkAdminService->resetEntraUserPassword($upn, $mailEmpfaenger);

    if ($success) {
        Flux::toast(
            heading: 'Passwort-Reset erfolgreich',
            text: 'Das Passwort wurde erfolgreich zurückgesetzt und wird per Mail an Sie gesendet.',
            variant: 'success'
        );
    } else {
        Flux::toast(
            heading: 'Fehler',
            text: 'Das Passwort konnte nicht zurückgesetzt werden.',
            variant: 'danger'
        );
    }
};

$removeFromGroup = function ($groupId) {
    if (! $this->selectedUser) {
        return;
    }

    $userService = app(MsGraphUserServiceInterface::class);
    $success = $userService->removeUserFromGroup($this->selectedUser['upn'], $groupId);

    if ($success) {
        // Aktualisiere die Gruppenliste
        $details = $userService->getUserDetails($this->selectedUser['upn']);
        
        if ($details && $details->user) {
            $this->selectedUser['groups'] = $details->groups 
                ? array_map(fn($g) => [
                    'displayName' => $g->getDisplayName() ?? $g->getId(),
                    'id' => $g->getId()
                ], $details->groups) 
                : [];
        }

        Flux::toast(
            heading: 'Erfolg',
            text: 'User wurde aus der Gruppe entfernt.',
            variant: 'success'
        );
    } else {
        Flux::toast(
            heading: 'Fehler',
            text: 'Fehler beim Entfernen des Users aus der Gruppe.',
            variant: 'danger'
        );
    }
};

?>
<div>
<x-intranet-app-msgraph::msgraph-layout heading="Msgraph App" subheading="Entra-User">
    <flux:card class="glass-card">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Suchen nach UPN, Vorname, Nachname..."
                class="w-full max-w-md"
            />

            @if($search)
                <flux:button
                    wire:click="resetSearch"
                    variant="ghost"
                    icon="x-mark"
                    size="sm"
                >
                    Suche zurücksetzen
                </flux:button>
            @endif
        </div>

        @if($loading)
            <div class="flex items-center justify-center py-12">
                <flux:icon.arrow-path class="size-8 animate-spin text-[#073070]/40 dark:text-white/40" />
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>UPN</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>E-Mail</flux:table.column>
                    <flux:table.column>Jobtitel</flux:table.column>
                    <flux:table.column>Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($users as $user)
                        <flux:table.row wire:key="user-{{ $user['id'] }}">
                            <flux:table.cell>{{ $user['userPrincipalName'] }}</flux:table.cell>
                            <flux:table.cell>{{ $user['displayName'] }}</flux:table.cell>
                            <flux:table.cell>{{ $user['mail'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $user['jobTitle'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    wire:click="showUserDetails('{{ $user['userPrincipalName'] }}')"
                                    size="xs"
                                    icon="eye"
                                    variant="ghost"
                                >
                                    Details
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-slate-400 dark:text-white/40">
                                @if($search)
                                    Keine Benutzer gefunden für "{{ $search }}"
                                @else
                                    Keine Benutzer gefunden
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div class="flex items-center justify-between">
                <div class="text-sm text-[#073070]/70 dark:text-white/60">
                    {{ $usersPerPage }} Benutzer pro Seite
                </div>
                <div class="flex gap-2">
                    <flux:button
                        wire:click="nextPage"
                        variant="ghost"
                        icon="chevron-right"
                        :disabled="!$nextLink"
                    >
                        Weitere laden
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
    </flux:card>
</x-intranet-app-msgraph::msgraph-layout>

<flux:modal name="user-details-modal" class="max-w-4xl">
    @if($selectedUser)
        <div class="space-y-6">
            <flux:heading size="lg">Benutzer-Details: {{ $selectedUser['displayName'] }}</flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:card class="glass-card">
                    <flux:heading size="md" class="mb-4">Basis-Informationen</flux:heading>
                    <div class="space-y-3">
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">UPN</flux:text>
                            <flux:text>{{ $selectedUser['upn'] }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Anzeigename</flux:text>
                            <flux:text>{{ $selectedUser['displayName'] }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">E-Mail</flux:text>
                            <flux:text>{{ $selectedUser['mail'] ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Jobtitel</flux:text>
                            <flux:text>{{ $selectedUser['jobTitle'] ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Abteilung</flux:text>
                            <flux:text>{{ $selectedUser['department'] ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Mobiltelefon</flux:text>
                            <flux:text>{{ $selectedUser['mobilePhone'] ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Bürostandort</flux:text>
                            <flux:text>{{ $selectedUser['officeLocation'] ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Account aktiviert</flux:text>
                            <div class="flex items-center gap-2">
                                @if($selectedUser['accountEnabled'])
                                    <flux:badge color="green">Ja</flux:badge>
                                @else
                                    <flux:button 
                                        wire:click="activateUser" 
                                        variant="primary"
                                        icon="check"
                                        size="sm"
                                    >
                                        Nein. Benutzer aktivieren!
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="glass-card">
                    <flux:heading size="md" class="mb-4">Zusätzliche Informationen</flux:heading>
                    <div class="space-y-3">
                        @if($selectedUser['manager'])
                            <div>
                                <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Vorgesetzter</flux:text>
                                <flux:text>{{ $selectedUser['manager'] }}</flux:text>
                            </div>
                        @endif

                        <div>
                            <flux:text class="font-semibold text-[#073070]/80 dark:text-white/70">Geschäftstelefone</flux:text>
                            @if($selectedUser['businessPhones'] && count($selectedUser['businessPhones']) > 0)
                                <flux:text>{{ implode(', ', $selectedUser['businessPhones']) }}</flux:text>
                            @else
                                <flux:text>-</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                @if($selectedUser['groups'])
                    <flux:card class="glass-card">
                        <flux:heading size="md" class="mb-4">Gruppen ({{ count($selectedUser['groups']) }})</flux:heading>
                        <div class="max-h-64 space-y-2 overflow-y-auto">
                            @forelse($selectedUser['groups'] as $group)
                                <div class="flex items-center justify-between rounded-lg border border-[#d0e3f9]/70 dark:border-white/15 bg-white/40 dark:bg-[#073070]/20 p-2">
                                    <flux:text class="text-sm">{{ $group['displayName'] }}</flux:text>
                                    <flux:button 
                                        size="xs" 
                                        wire:confirm="Möchten Sie den User wirklich aus dieser Gruppe entfernen?"
                                        wire:click="removeFromGroup('{{ $group['id'] }}')" 
                                        variant="danger"
                                    >
                                        <flux:icon name="trash" />
                                    </flux:button>
                                </div>
                            @empty
                                <flux:text class="text-slate-400 dark:text-white/40">Keine Gruppen</flux:text>
                            @endforelse
                        </div>
                    </flux:card>
                @endif

                @if($selectedUser['teams'])
                    <flux:card class="glass-card">
                        <flux:heading size="md" class="mb-4">Teams ({{ count($selectedUser['teams']) }})</flux:heading>
                        <div class="max-h-64 space-y-2 overflow-y-auto">
                            @forelse($selectedUser['teams'] as $team)
                                <div class="rounded-lg border border-[#d0e3f9]/70 dark:border-white/15 bg-white/30 dark:bg-[#073070]/15 p-2">
                                    <flux:text class="text-sm">{{ $team['displayName'] }}</flux:text>
                                </div>
                            @empty
                                <flux:text class="text-slate-400 dark:text-white/40">Keine Teams</flux:text>
                            @endforelse
                        </div>
                    </flux:card>
                @endif
            </div>

			{{-- Authentifizierungsmethoden (minimalistisch) --}}
			<flux:card class="glass-card">
				<flux:heading size="md" class="mb-4">Authentifizierungsmethoden</flux:heading>
				@if($this->authMethods && count($this->authMethods) > 0)
					<div class="space-y-3">
						@foreach($this->authMethods as $m)
							<div class="space-y-2 rounded-lg border border-[#d0e3f9]/70 dark:border-white/15 bg-white/30 dark:bg-[#073070]/15 p-2">
								<div class="flex items-center justify-between">
									<flux:text class="text-sm font-medium">{{ $m->getODataType() }}</flux:text>
									@if($m->getODataType() !== '#microsoft.graph.passwordAuthenticationMethod')
										<flux:button
											size="xs"
											variant="danger"
											wire:confirm="Methode wirklich löschen?"
											wire:click="deleteAuthMethod('{{ str_replace('#', '', $m->getODataType()) }}', '{{ $m->getId() }}')"
										>
											<flux:icon name="trash" />
										</flux:button>
									@else
										<flux:badge color="zinc">nicht löschbar</flux:badge>
									@endif
								</div>
								@php($details = $this->getAuthMethod($m->getODataType(), $m->getId()))
								<div class="grid gap-1 md:grid-cols-2">
									@foreach($details as $k => $v)
										<flux:text class="text-xs text-[#073070]/70 dark:text-white/60">{{ $k }}: {{ $v }}</flux:text>
									@endforeach
								</div>
							</div>
						@endforeach
					</div>
				@else
					<flux:text class="text-slate-400 dark:text-white/40">Keine Methoden</flux:text>
				@endif
			</flux:card>

            <div class="flex justify-between gap-2">
                <flux:button 
                    wire:click="resetPassword" 
                    variant="danger"
                    icon="key"
                >
                    Passwort Reset
                </flux:button>
                <flux:button variant="ghost" x-on:click="$flux.modal('user-details-modal').close()">
                    Schließen
                </flux:button>
            </div>
        </div>
    @endif
</flux:modal>
</div>