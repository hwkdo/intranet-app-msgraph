<div>
    <x-intranet-app-msgraph::msgraph-layout heading="Auslandszugriff" subheading="Verwaltung der Auslandseinsatz-Gruppenmitglieder">
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Auslandseinsatz-Gruppe</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-white/50">
                    Mitglieder mit Zugriffsrecht für Auslandseinsätze
                </flux:text>
            </div>
            <flux:button
                wire:click="toggleAddForm"
                variant="primary"
                icon="{{ $showAddForm ? 'x-mark' : 'plus' }}"
            >
                {{ $showAddForm ? 'Abbrechen' : 'Hinzufügen' }}
            </flux:button>
        </div>

        {{-- Hinzufügen-Formular --}}
        @if($showAddForm)
            <flux:card class="space-y-4">
                <flux:heading size="md">Benutzer hinzufügen</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Benutzer suchen</flux:label>
                        <div class="relative">
                            <flux:input
                                wire:model.live.debounce.300ms="search"
                                placeholder="Name oder UPN eingeben..."
                                icon="magnifying-glass"
                                autocomplete="off"
                            />
                            @if(!empty($searchResults))
                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-800">
                                    @foreach($searchResults as $result)
                                        <button
                                            type="button"
                                            wire:click="selectUser('{{ $result['upn'] }}', '{{ addslashes($result['displayName']) }}', '{{ $result['id'] }}')"
                                            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-white/5 {{ !$loop->last ? 'border-b border-zinc-100 dark:border-white/10' : '' }}"
                                        >
                                            <flux:icon.user class="size-4 shrink-0 text-zinc-400" />
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $result['displayName'] }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-white/50">{{ $result['upn'] }}</div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @if($selectedUser)
                            <div class="mt-2 flex items-center gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-white/5">
                                <flux:icon.check-circle class="size-4 text-green-500" />
                                <span class="text-sm text-zinc-700 dark:text-white/80">
                                    {{ $selectedUser['displayName'] }}
                                    <span class="text-zinc-400 dark:text-white/40">({{ $selectedUser['upn'] }})</span>
                                </span>
                                <button
                                    type="button"
                                    wire:click="$set('selectedUser', null)"
                                    class="ml-auto text-zinc-400 hover:text-zinc-600 dark:hover:text-white/60"
                                >
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </div>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Aufenthaltszeitraum</flux:label>
                        <flux:date-picker
                            mode="range"
                            min="today"
                            wire:model="zeitraum"
                            with-inputs
                            max-range="365"
                        />
                        <flux:description>Von und bis zum Aufenthalt im Ausland — der Gruppenzugriff wird automatisch zum Startdatum aktiviert und nach dem Enddatum beendet.</flux:description>
                    </flux:field>
                </div>

                <div class="flex justify-end">
                    <flux:button
                        wire:click="addUser"
                        variant="primary"
                        icon="user-plus"
                        :disabled="!$selectedUser"
                    >
                        Eintrag anlegen
                    </flux:button>
                </div>
            </flux:card>
        @endif

        @php
            $managed = $this->entries['managed'] ?? [];
            $untracked = $this->entries['untracked'] ?? [];
            $hasEntries = count($managed) > 0 || count($untracked) > 0;
        @endphp

        {{-- Verwaltete Einträge --}}
        <flux:heading size="md">Verwaltete Aufenthalte</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>UPN</flux:table.column>
                <flux:table.column>Von</flux:table.column>
                <flux:table.column>Bis</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($managed as $entry)
                    <flux:table.row wire:key="managed-{{ $entry['membershipId'] }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.user class="size-4 shrink-0 text-zinc-400" />
                                <span class="font-medium">{{ $entry['displayName'] ?: $entry['upn'] }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500 dark:text-white/50">
                            {{ $entry['upn'] }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $entry['startsAt'] }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $entry['endsAt'] }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $badgeColor = match ($entry['status']) {
                                    'geplant' => 'blue',
                                    'aktiv' => 'green',
                                    'abgelaufen' => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge color="{{ $badgeColor }}" size="sm">{{ $entry['statusLabel'] }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                wire:click="removeMembership({{ $entry['membershipId'] }})"
                                wire:confirm="Eintrag für '{{ $entry['displayName'] ?: $entry['upn'] }}' wirklich entfernen?"
                                size="xs"
                                variant="danger"
                                icon="user-minus"
                            >
                                Entfernen
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-400 dark:text-white/40">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.users class="size-8" />
                                <span>Keine verwalteten Einträge</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if(count($untracked) > 0)
            <div class="space-y-3 pt-4">
                <flux:heading size="md">Nicht verwaltete Azure-Mitglieder</flux:heading>
                <flux:callout variant="warning" icon="exclamation-triangle">
                    Diese Konten sind in der Entra-Gruppe, wurden aber nicht über diese Oberfläche angelegt.
                </flux:callout>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>UPN</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($untracked as $member)
                            <flux:table.row wire:key="untracked-{{ $member['id'] ?: $member['upn'] }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:icon.user class="size-4 shrink-0 text-zinc-400" />
                                        <span class="font-medium">{{ $member['displayName'] ?: $member['upn'] }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500 dark:text-white/50">
                                    {{ $member['upn'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="zinc" size="sm">{{ $member['statusLabel'] }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        wire:click="removeUntrackedMember(@js($member['upn']))"
                                        wire:confirm="Benutzer '{{ $member['displayName'] ?: $member['upn'] }}' aus der Gruppe entfernen?"
                                        size="xs"
                                        variant="danger"
                                        icon="user-minus"
                                    >
                                        Entfernen
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        @if(! $hasEntries && ! $showAddForm)
            <flux:text class="text-sm text-zinc-500 dark:text-white/50">
                Lege über „Hinzufügen“ einen neuen Auslandseinsatz an.
            </flux:text>
        @endif

    </div>
</x-intranet-app-msgraph::msgraph-layout>
</div>
