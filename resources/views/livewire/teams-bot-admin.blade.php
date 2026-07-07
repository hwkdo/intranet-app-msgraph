<div class="space-y-6">
    <flux:card class="glass-card">
        <flux:heading size="lg" class="mb-4">Teams Bot Status</flux:heading>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-[#d0e3f9]/80 dark:border-white/10 bg-[#d0e3f9]/40 dark:bg-[#073070]/40 p-4">
                <flux:text class="font-semibold">Aktiviert</flux:text>
                <div class="mt-2">
                    @if($this->botEnabled)
                        <flux:badge color="green">Ja</flux:badge>
                    @else
                        <flux:badge color="zinc">Nein</flux:badge>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-[#d0e3f9]/80 dark:border-white/10 bg-[#d0e3f9]/40 dark:bg-[#073070]/40 p-4">
                <flux:text class="font-semibold">teams-sdk-rest</flux:text>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if(! $this->sdkRestConfigured)
                        <flux:badge color="zinc">Nicht konfiguriert</flux:badge>
                    @elseif($this->sdkRestHealth['healthy'])
                        <flux:badge color="green">Erreichbar</flux:badge>
                    @else
                        <flux:badge color="red">Nicht erreichbar</flux:badge>
                    @endif

                    <flux:button
                        wire:click="refreshSdkHealth"
                        wire:target="refreshSdkHealth"
                        wire:loading.attr="disabled"
                        size="xs"
                        variant="ghost"
                        icon="arrow-path"
                    >
                        <span wire:loading.remove wire:target="refreshSdkHealth">Prüfen</span>
                        <span wire:loading wire:target="refreshSdkHealth">Prüfe…</span>
                    </flux:button>
                </div>
                @if($this->sdkRestHealth['service'])
                    <flux:text class="mt-2 text-xs text-zinc-500 dark:text-white/50">
                        Service: {{ $this->sdkRestHealth['service'] }}
                    </flux:text>
                @endif
            </div>

            <div class="rounded-xl border border-[#d0e3f9]/80 dark:border-white/10 bg-[#d0e3f9]/40 dark:bg-[#073070]/40 p-4">
                <flux:text class="font-semibold">Bot App ID</flux:text>
                <flux:text class="mt-2 font-mono text-sm break-all">
                    {{ $this->botAppId ?? '—' }}
                </flux:text>
            </div>

            <div class="rounded-xl border border-[#d0e3f9]/80 dark:border-white/10 bg-[#d0e3f9]/40 dark:bg-[#073070]/40 p-4">
                <flux:text class="font-semibold">Aktive Conversations</flux:text>
                <flux:text size="xl" class="mt-2 font-semibold text-[#073070] dark:text-white">
                    {{ $this->activeConversationCount }}
                </flux:text>
            </div>
        </div>

        @if($this->sdkRestConfigured)
            <flux:text class="mt-4 font-mono text-xs break-all text-zinc-500 dark:text-white/50">
                {{ $this->sdkRestHealth['base_url'] }}
            </flux:text>
        @endif

        <flux:callout class="mt-4" icon="information-circle" variant="secondary">
            Ausgehende Nachrichten und Webhooks laufen über den Docker-Service
            <span class="font-mono">teams-sdk-rest</span>. Die Willkommensnachricht wird im Container
            über <span class="font-mono">WELCOME_MESSAGE</span> konfiguriert.
        </flux:callout>
    </flux:card>

    <flux:card class="glass-card">
        <flux:heading size="lg" class="mb-4">Benutzer & Testnachricht</flux:heading>

        <div class="space-y-4">
            <div class="relative">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    label="Entra-Benutzer suchen"
                    placeholder="Name oder UPN eingeben…"
                />

                @if($searchResults !== [])
                    <div class="absolute z-20 mt-1 w-full rounded-xl border border-[#d0e3f9] bg-white shadow-lg dark:border-white/10 dark:bg-[#04214e]">
                        @foreach($searchResults as $result)
                            <button
                                type="button"
                                class="block w-full px-4 py-3 text-left hover:bg-[#d0e3f9]/50 dark:hover:bg-white/5"
                                wire:click="selectUser(@js($result['upn']), @js($result['displayName']), @js($result['id']))"
                            >
                                <div class="font-medium">{{ $result['displayName'] ?: $result['upn'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-white/50">{{ $result['upn'] }}</div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($selectedUser)
                <div class="flex items-center justify-between rounded-lg border border-[#d0e3f9]/80 dark:border-white/10 p-3">
                    <div>
                        <flux:text class="font-semibold">
                            {{ $selectedUser['displayName'] ?: $selectedUser['upn'] }}
                        </flux:text>
                        <flux:text class="text-sm text-zinc-500">{{ $selectedUser['upn'] }}</flux:text>
                    </div>
                    <flux:button variant="ghost" size="sm" wire:click="clearSelectedUser">Entfernen</flux:button>
                </div>
            @endif

            <flux:textarea
                wire:model="testMessage"
                label="Testnachricht"
                rows="3"
            />

            <div class="flex flex-wrap gap-2">
                <flux:button
                    wire:click="installBot"
                    wire:target="installBot"
                    wire:loading.attr="disabled"
                    icon="arrow-down-tray"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="installBot">Bot installieren</span>
                    <span wire:loading wire:target="installBot">Installiere…</span>
                </flux:button>

                <flux:button
                    wire:click="sendTestMessage"
                    wire:target="sendTestMessage"
                    wire:loading.attr="disabled"
                    icon="paper-airplane"
                >
                    <span wire:loading.remove wire:target="sendTestMessage">Testnachricht senden</span>
                    <span wire:loading wire:target="sendTestMessage">Sende…</span>
                </flux:button>

                <flux:button
                    wire:click="installAllUsers"
                    wire:target="installAllUsers"
                    wire:loading.attr="disabled"
                    icon="users"
                    variant="ghost"
                    wire:confirm="Bot-Installation für alle Entra-Benutzer starten?"
                >
                    <span wire:loading.remove wire:target="installAllUsers">Alle Benutzer installieren</span>
                    <span wire:loading wire:target="installAllUsers">Starte…</span>
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card class="glass-card">
        <flux:heading size="lg" class="mb-4">Kanal & Testnachricht</flux:heading>

        <flux:callout class="mb-4" icon="information-circle" variant="secondary">
            Zuerst ein <span class="font-semibold">Team</span> suchen und auswählen, dann den
            <span class="font-semibold">Kanal</span> wählen. Der Bot muss im Team installiert sein,
            damit Kanal-Nachrichten zugestellt werden können.
        </flux:callout>

        <div class="space-y-4">
            <div class="relative">
                <flux:input
                    wire:model.live.debounce.500ms="teamSearch"
                    label="Team suchen"
                    placeholder="Team-Namen eingeben…"
                />

                <div wire:loading wire:target="teamSearch" class="absolute right-3 top-9">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin text-zinc-400" />
                </div>

                @if($teamSearchResults !== [])
                    <div class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl border border-[#d0e3f9] bg-white shadow-lg dark:border-white/10 dark:bg-[#04214e]">
                        @foreach($teamSearchResults as $result)
                            <button
                                type="button"
                                class="block w-full px-4 py-3 text-left hover:bg-[#d0e3f9]/50 dark:hover:bg-white/5"
                                wire:click="selectTeam(@js($result['teamId']), @js($result['teamName']))"
                            >
                                <div class="font-medium">{{ $result['teamName'] }}</div>
                                <div class="text-xs font-mono text-zinc-500 dark:text-white/50">
                                    {{ $result['teamId'] }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($selectedTeam)
                <div class="flex items-center justify-between rounded-lg border border-[#d0e3f9]/80 dark:border-white/10 p-3">
                    <div>
                        <flux:text class="font-semibold">{{ $selectedTeam['teamName'] }}</flux:text>
                        <flux:text class="text-sm font-mono text-zinc-500">Team: {{ $selectedTeam['teamId'] }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button
                            wire:click="installBotForTeam"
                            wire:target="installBotForTeam"
                            wire:loading.attr="disabled"
                            size="sm"
                            icon="arrow-down-tray"
                        >
                            <span wire:loading.remove wire:target="installBotForTeam">Bot installieren / aktualisieren</span>
                            <span wire:loading wire:target="installBotForTeam">Läuft…</span>
                        </flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="clearSelectedTeam">Entfernen</flux:button>
                    </div>
                </div>

                <flux:callout icon="information-circle" variant="secondary" class="text-sm">
                    Bei „BotNotInConversationRoster" bzw. 403 zuerst hier den Bot installieren/aktualisieren.
                    Das aktualisiert eine im Team bereits vorhandene ältere App-Version auf die neueste
                    (nötig, damit der Bot den Team-Scope erhält).
                </flux:callout>

                <flux:select
                    wire:model="selectedChannelId"
                    label="Kanal"
                    placeholder="Kanal auswählen…"
                >
                    @foreach($teamChannels as $channel)
                        <flux:select.option value="{{ $channel['channelId'] }}">
                            {{ $channel['channelName'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if($teamChannels === [])
                    <flux:text class="text-sm text-zinc-500">
                        Für dieses Team wurden keine Kanäle gefunden.
                    </flux:text>
                @endif
            @endif

            <flux:textarea
                wire:model="channelTestMessage"
                label="Kanal-Testnachricht"
                rows="3"
            />

            <div class="flex flex-wrap gap-2">
                <flux:button
                    wire:click="sendChannelTestMessage"
                    wire:target="sendChannelTestMessage"
                    wire:loading.attr="disabled"
                    icon="paper-airplane"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="sendChannelTestMessage">Testnachricht an Kanal senden</span>
                    <span wire:loading wire:target="sendChannelTestMessage">Sende…</span>
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card class="glass-card">
        <flux:heading size="lg" class="mb-4">Gruppenchat & Testnachricht</flux:heading>

        <flux:callout class="mb-4" icon="information-circle" variant="secondary">
            Gruppenchats haben oft keinen Namen, daher werden sie über einen
            <span class="font-semibold">Teilnehmer</span> gesucht: Benutzer auswählen, dann dessen
            Gruppenchats laden. Der Bot muss im Gruppenchat installiert sein
            (Manifest-Scope <span class="font-mono">groupChat</span> + Berechtigung
            <span class="font-mono">TeamsAppInstallation.ReadWriteForChat.All</span>).
        </flux:callout>

        <div class="space-y-4">
            <div class="relative">
                <flux:input
                    wire:model.live.debounce.300ms="chatUserSearch"
                    label="Teilnehmer suchen"
                    placeholder="Name oder UPN eingeben…"
                />

                @if($chatUserSearchResults !== [])
                    <div class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl border border-[#d0e3f9] bg-white shadow-lg dark:border-white/10 dark:bg-[#04214e]">
                        @foreach($chatUserSearchResults as $result)
                            <button
                                type="button"
                                class="block w-full px-4 py-3 text-left hover:bg-[#d0e3f9]/50 dark:hover:bg-white/5"
                                wire:click="selectChatUser(@js($result['upn']), @js($result['displayName']), @js($result['id']))"
                            >
                                <div class="font-medium">{{ $result['displayName'] ?: $result['upn'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-white/50">{{ $result['upn'] }}</div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($selectedChatUser)
                <div class="flex items-center justify-between rounded-lg border border-[#d0e3f9]/80 dark:border-white/10 p-3">
                    <div>
                        <flux:text class="font-semibold">
                            {{ $selectedChatUser['displayName'] ?: $selectedChatUser['upn'] }}
                        </flux:text>
                        <flux:text class="text-sm text-zinc-500">{{ $selectedChatUser['upn'] }}</flux:text>
                    </div>
                    <flux:button variant="ghost" size="sm" wire:click="clearSelectedChatUser">Entfernen</flux:button>
                </div>

                <flux:select
                    wire:model="selectedChatId"
                    label="Gruppenchat"
                    placeholder="Gruppenchat auswählen…"
                >
                    @foreach($groupChats as $chat)
                        <flux:select.option value="{{ $chat['chatId'] }}">
                            {{ $chat['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if($groupChats === [])
                    <flux:text class="text-sm text-zinc-500">
                        Für diesen Benutzer wurden keine Gruppenchats gefunden.
                    </flux:text>
                @endif
            @endif

            <flux:textarea
                wire:model="chatTestMessage"
                label="Gruppenchat-Testnachricht"
                rows="3"
            />

            <div class="flex flex-wrap gap-2">
                <flux:button
                    wire:click="installBotForChat"
                    wire:target="installBotForChat"
                    wire:loading.attr="disabled"
                    icon="arrow-down-tray"
                >
                    <span wire:loading.remove wire:target="installBotForChat">Bot installieren / aktualisieren</span>
                    <span wire:loading wire:target="installBotForChat">Läuft…</span>
                </flux:button>

                <flux:button
                    wire:click="sendChatTestMessage"
                    wire:target="sendChatTestMessage"
                    wire:loading.attr="disabled"
                    icon="paper-airplane"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="sendChatTestMessage">Testnachricht an Gruppenchat senden</span>
                    <span wire:loading wire:target="sendChatTestMessage">Sende…</span>
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card class="glass-card">
        <flux:heading size="lg" class="mb-4">Conversations</flux:heading>

        @if($this->conversations->isEmpty())
            <flux:text>Keine Teams-Bot-Conversations gespeichert.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>UPN</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Installiert</flux:table.column>
                    <flux:table.column>Letzte Nachricht</flux:table.column>
                    <flux:table.column>Fehler</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->conversations as $conversation)
                        <flux:table.row wire:key="teams-bot-conversation-{{ $conversation->id }}">
                            <flux:table.cell>{{ $conversation->upn ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $conversation->display_name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $statusColor = match($conversation->status->value) {
                                        'active' => 'green',
                                        'pending' => 'amber',
                                        'failed' => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" color="{{ $statusColor }}">
                                    {{ $conversation->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $conversation->installed_at?->format('d.m.Y H:i') ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $conversation->last_message_at?->format('d.m.Y H:i') ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate" title="{{ $conversation->last_error }}">
                                {{ $conversation->last_error ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
