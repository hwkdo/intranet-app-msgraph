<div class="h-full min-h-0 flex flex-col">
    <x-intranet-app-base::dashboard.widget-card
        title="Nächste Azure-App-Abläufe"
        :description="'Secrets und Zertifikate mit Ablaufdatum (max. '.$this->itemLimit().', noch nicht abgelaufen)'"
    >
        @forelse ($this->items as $row)
            <a
                href="{{ route('apps.msgraph.azure-apps.index') }}"
                wire:navigate
                class="group block cursor-pointer rounded-md border border-zinc-200 bg-white/60 px-3 py-2 transition-colors duration-150 hover:bg-zinc-100 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-900 group-hover:text-zinc-950 dark:text-white dark:group-hover:text-white">
                            {{ \Illuminate\Support\Str::limit($row['appDisplayName'], 42) }}
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-white/70">
                            @if (($row['credentialType'] ?? 'secret') === 'certificate')
                                <flux:badge color="zinc" size="sm">Zertifikat</flux:badge>
                            @else
                                <flux:badge color="sky" size="sm">Secret</flux:badge>
                            @endif
                            <span class="truncate">{{ \Illuminate\Support\Str::limit($row['secretDisplayName'], 40) }}</span>
                        </div>
                    </div>
                    <div class="shrink-0 text-right text-xs">
                        <div class="font-medium text-zinc-800 dark:text-white">
                            {{ $row['endDateTime']->format('d.m.Y') }}
                        </div>
                        <div class="@if($row['daysUntilExpiry'] <= 14) text-amber-600 dark:text-amber-400 @else text-zinc-500 dark:text-white/60 @endif">
                            {{ $row['daysUntilExpiry'] }} {{ $row['daysUntilExpiry'] === 1 ? 'Tag' : 'Tage' }}
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <flux:text class="text-zinc-500 dark:text-white/80">
                Keine anstehenden Abläufe – alle erkannten Secrets/Zertifikate sind entweder ohne Ablaufdatum oder bereits abgelaufen.
            </flux:text>
        @endforelse

        @if (count($this->items) > 0)
            <div class="pt-1">
                <flux:button variant="ghost" size="sm" :href="route('apps.msgraph.azure-apps.index')" wire:navigate>
                    Alle Azure-Apps anzeigen
                </flux:button>
            </div>
        @endif
    </x-intranet-app-base::dashboard.widget-card>
</div>
