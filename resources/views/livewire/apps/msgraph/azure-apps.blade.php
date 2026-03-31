<div>
<x-intranet-app-msgraph::msgraph-layout heading="Msgraph App" subheading="Azure Apps">
    <flux:card class="glass-card">
        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="App-Name suchen..."
                    icon="magnifying-glass"
                    class="w-full max-w-md"
                />

                <div class="flex items-center gap-3">
                    <flux:field variant="inline">
                        <flux:label>Abgelaufene anzeigen</flux:label>
                        <flux:switch wire:model.live="showExpired" />
                    </flux:field>

                @if($search)
                    <flux:button
                        wire:click="$set('search', '')"
                        variant="ghost"
                        icon="x-mark"
                        size="sm"
                    >
                        Suche zurücksetzen
                    </flux:button>
                @endif
                </div>
            </div>

            <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>App-Name</flux:table.column>
                    <flux:table.column>Client-ID</flux:table.column>
                    <flux:table.column>Typ</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>
                        <button type="button" wire:click="sortBy('end_date')" class="inline-flex items-center gap-1">
                            <span>Ablauf</span>
                            @if($sortField === 'end_date')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column>
                        <button type="button" wire:click="sortBy('remaining_days')" class="inline-flex items-center gap-1">
                            <span>Verbleibend</span>
                            @if($sortField === 'remaining_days')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->rows as $row)
                        <flux:table.row wire:key="secret-{{ $row['appInternalId'] }}-{{ $row['keyId'] }}">
                                <flux:table.cell class="max-w-[12rem]">
                                    <flux:tooltip :content="$row['appDisplayName']" position="top">
                                        <flux:text class="font-medium truncate block max-w-48">
                                            {{ \Illuminate\Support\Str::limit($row['appDisplayName'], 30) }}
                                        </flux:text>
                                    </flux:tooltip>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[10rem]">
                                    <flux:tooltip :content="$row['appId']" position="top">
                                        <flux:text class="font-mono text-xs text-[#073070]/60 dark:text-white/50 truncate block max-w-40">
                                            {{ \Illuminate\Support\Str::limit($row['appId'], 36) }}
                                        </flux:text>
                                    </flux:tooltip>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if(($row['credentialType'] ?? 'secret') === 'certificate')
                                        <flux:badge color="zinc" size="sm">Zertifikat</flux:badge>
                                    @else
                                        <flux:badge color="sky" size="sm">Secret</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[12rem]">
                                    <flux:tooltip :content="$row['displayName']" position="top">
                                        <flux:text class="truncate block max-w-48">
                                            {{ \Illuminate\Support\Str::limit($row['displayName'], 30) }}
                                        </flux:text>
                                    </flux:tooltip>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($row['endDateTime'])
                                        {{ $row['endDateTime']->format('d.m.Y') }}
                                    @else
                                        <flux:text class="text-[#073070]/40 dark:text-white/30">–</flux:text>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($row['daysUntilExpiry'] !== null)
                                        @if($row['daysUntilExpiry'] < 0)
                                            <flux:text class="text-red-600 dark:text-red-400 font-medium">
                                                Abgelaufen
                                            </flux:text>
                                        @else
                                            <flux:text>{{ $row['daysUntilExpiry'] }} Tage</flux:text>
                                        @endif
                                    @else
                                        <flux:text class="text-[#073070]/40 dark:text-white/30">–</flux:text>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    @php
                                        $badgeColor = match($row['status']) {
                                            'expired' => 'red',
                                            'expiring_soon' => 'amber',
                                            'active' => 'green',
                                            default => 'zinc',
                                        };
                                        $badgeLabel = match($row['status']) {
                                            'expired' => 'Abgelaufen',
                                            'expiring_soon' => 'Läuft bald ab',
                                            'active' => 'Aktiv',
                                            default => 'Unbekannt',
                                        };
                                    @endphp
                                    <flux:badge :color="$badgeColor" size="sm">
                                        {{ $badgeLabel }}
                                    </flux:badge>
                                </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center text-slate-400 dark:text-white/40 py-8">
                                @if($search)
                                    Keine Einträge gefunden für „{{ $search }}"
                                @else
                                    Keine Einträge gefunden
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            </div>

            @if(count($this->rows) > 0)
                <div class="text-sm text-[#073070]/60 dark:text-white/50">
                    {{ count($this->rows) }} {{ count($this->rows) === 1 ? 'Eintrag' : 'Einträge' }} gefunden
                </div>
            @endif
        </div>
    </flux:card>
</x-intranet-app-msgraph::msgraph-layout>
</div>
