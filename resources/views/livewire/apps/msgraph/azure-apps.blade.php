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

            <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>App-Name</flux:table.column>
                    <flux:table.column>Client-ID</flux:table.column>
                    <flux:table.column>Typ</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Ablauf</flux:table.column>
                    <flux:table.column>Verbleibend</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->apps as $app)
                        @foreach($app['secrets'] as $index => $secret)
                            <flux:table.row wire:key="secret-{{ $app['id'] }}-{{ $secret['keyId'] }}">
                                @if($index === 0)
                                    <flux:table.cell class="max-w-[12rem]">
                                        <flux:tooltip :content="$app['displayName']" position="top">
                                            <flux:text class="font-medium truncate block max-w-48">
                                                {{ \Illuminate\Support\Str::limit($app['displayName'], 30) }}
                                            </flux:text>
                                        </flux:tooltip>
                                    </flux:table.cell>
                                    <flux:table.cell class="max-w-[10rem]">
                                        <flux:tooltip :content="$app['appId']" position="top">
                                            <flux:text class="font-mono text-xs text-[#073070]/60 dark:text-white/50 truncate block max-w-40">
                                                {{ \Illuminate\Support\Str::limit($app['appId'], 36) }}
                                            </flux:text>
                                        </flux:tooltip>
                                    </flux:table.cell>
                                @else
                                    <flux:table.cell></flux:table.cell>
                                    <flux:table.cell></flux:table.cell>
                                @endif

                                <flux:table.cell>
                                    @if(($secret['credentialType'] ?? 'secret') === 'certificate')
                                        <flux:badge color="zinc" size="sm">Zertifikat</flux:badge>
                                    @else
                                        <flux:badge color="sky" size="sm">Secret</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[12rem]">
                                    <flux:tooltip :content="$secret['displayName']" position="top">
                                        <flux:text class="truncate block max-w-48">
                                            {{ \Illuminate\Support\Str::limit($secret['displayName'], 30) }}
                                        </flux:text>
                                    </flux:tooltip>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($secret['endDateTime'])
                                        {{ $secret['endDateTime']->format('d.m.Y') }}
                                    @else
                                        <flux:text class="text-[#073070]/40 dark:text-white/30">–</flux:text>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($secret['daysUntilExpiry'] !== null)
                                        @if($secret['daysUntilExpiry'] < 0)
                                            <flux:text class="text-red-600 dark:text-red-400 font-medium">
                                                Abgelaufen
                                            </flux:text>
                                        @else
                                            <flux:text>{{ $secret['daysUntilExpiry'] }} Tage</flux:text>
                                        @endif
                                    @else
                                        <flux:text class="text-[#073070]/40 dark:text-white/30">–</flux:text>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    @php
                                        $badgeColor = match($secret['status']) {
                                            'expired' => 'red',
                                            'expiring_soon' => 'amber',
                                            'active' => 'green',
                                            default => 'zinc',
                                        };
                                        $badgeLabel = match($secret['status']) {
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
                        @endforeach
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center text-slate-400 dark:text-white/40 py-8">
                                @if($search)
                                    Keine Apps gefunden für „{{ $search }}"
                                @else
                                    Keine App-Registrierungen mit Secrets oder Zertifikaten gefunden
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            </div>

            @if(count($this->apps) > 0)
                <div class="text-sm text-[#073070]/60 dark:text-white/50">
                    {{ count($this->apps) }} {{ count($this->apps) === 1 ? 'App' : 'Apps' }} gefunden
                </div>
            @endif
        </div>
    </flux:card>
</x-intranet-app-msgraph::msgraph-layout>
</div>
