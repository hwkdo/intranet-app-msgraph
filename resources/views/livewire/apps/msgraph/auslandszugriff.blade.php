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
                        <flux:label>Anzahl Tage <span class="text-zinc-400 dark:text-white/40">(optional)</span></flux:label>
                        <flux:input
                            wire:model="anzahlTage"
                            type="number"
                            min="1"
                            max="365"
                            placeholder="Unbegrenzt"
                            icon="calendar-days"
                        />
                        <flux:description>Mitgliedschaft läuft nach dieser Anzahl Tage automatisch ab.</flux:description>
                    </flux:field>
                </div>

                <div class="flex justify-end">
                    <flux:button
                        wire:click="addUser"
                        variant="primary"
                        icon="user-plus"
                        :disabled="!$selectedUser"
                    >
                        Zur Gruppe hinzufügen
                    </flux:button>
                </div>
            </flux:card>
        @endif

        {{-- Mitgliederliste --}}
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>UPN</flux:table.column>
                <flux:table.column>Hinzugefügt</flux:table.column>
                <flux:table.column>Läuft ab</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->members as $member)
                    <flux:table.row wire:key="member-{{ $member['id'] ?: $member['upn'] }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.user class="size-4 shrink-0 text-zinc-400" />
                                <span class="font-medium">{{ $member['displayName'] ?: $member['upn'] }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500 dark:text-white/50">
                            {{ $member['upn'] ?: '–' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            {{ $member['addedAt'] }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($member['expiresAt'])
                                <flux:badge
                                    color="{{ \Carbon\Carbon::parse($member['expiresAt'])->isBefore(now()->addDays(3)) ? 'red' : 'yellow' }}"
                                    size="sm"
                                    icon="clock"
                                >
                                    {{ $member['expiresAt'] }}
                                </flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Unbegrenzt</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                wire:click="removeUser('{{ $member['upn'] }}')"
                                wire:confirm="Benutzer '{{ $member['displayName'] ?: $member['upn'] }}' aus der Gruppe entfernen?"
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
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-400 dark:text-white/40">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.users class="size-8" />
                                <span>Keine Mitglieder in dieser Gruppe</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

    </div>
</x-intranet-app-msgraph::msgraph-layout>
</div>