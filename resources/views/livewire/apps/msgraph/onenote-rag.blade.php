<div>
    <x-intranet-app-msgraph::msgraph-layout heading="OneNote-RAG" subheading="Seiten nach LightRAG übernehmen">
        <div class="space-y-6">
            @if($onenoteAccountUpn)
                <flux:callout icon="check-circle" variant="success">
                    <flux:callout.text>OneNote-Konto verbunden: {{ $onenoteAccountUpn }}</flux:callout.text>
                </flux:callout>
            @endif
            <flux:card class="glass-card space-y-3">
                <flux:heading size="sm">OneNote-Konto</flux:heading>
                <flux:text>Die Seiten liegen im OneDrive von filer@hwkdoedu.onmicrosoft.com. Die Anmeldung ist die normale Microsoft-Anmeldung. Im Kontodialog dieses Konto wählen, nicht das eigene Intranet-Konto. Die Intranet-Sitzung bleibt dabei unverändert.</flux:text>
                <flux:button variant="primary" href="{{ route('apps.msgraph.onenote-rag.connect') }}">
                    {{ $onenoteAccountUpn ? 'Neu verbinden' : 'Microsoft-Anmeldung starten' }}
                </flux:button>
            </flux:card>

            @if($requestUrl !== '')
                <flux:card class="glass-card">
                    <flux:heading size="sm">Abgerufene URL</flux:heading>
                    <flux:text class="mt-2 break-all font-mono text-xs">{{ $requestUrl }}</flux:text>
                </flux:card>
            @endif

            @if($errorMessage)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text class="whitespace-pre-line break-all">{{ $errorMessage }}</flux:callout.text>
                </flux:callout>
            @endif

            @if($infoMessage)
                <flux:callout variant="success" icon="arrow-up-tray">
                    <flux:callout.text>{{ $infoMessage }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:card class="glass-card">
                <div class="grid gap-4 md:grid-cols-[12rem_1fr_auto] md:items-end">
                    <flux:select wire:model.live="sourceType" label="Quelle">
                        <flux:select.option value="user">Benutzer</flux:select.option>
                        <flux:select.option value="group">Gruppe</flux:select.option>
                    </flux:select>

                    <flux:input
                        wire:model="ownerQuery"
                        label="{{ $sourceType === 'group' ? 'Gruppenname' : 'Benutzer-UPN' }}"
                        placeholder="{{ $sourceType === 'group' ? 'Team Besprechungen' : 'name@hwk-do.de' }}"
                    />

                    <flux:button variant="primary" wire:click="loadNotebooks" wire:loading.attr="disabled">
                        Bücher laden
                    </flux:button>
                </div>
            </flux:card>

            @if($notebooks !== [])
                <div class="space-y-3">
                    <flux:heading size="lg">Notizbücher</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($notebooks as $notebook)
                            <flux:button
                                wire:key="notebook-{{ $notebook['id'] }}"
                                wire:click="selectNotebook({{ \Illuminate\Support\Js::from($notebook['id']) }})"
                                :variant="$notebookId === $notebook['id'] ? 'primary' : 'filled'"
                                size="sm"
                            >
                                {{ $notebook['name'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($sections !== [])
                <div class="space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <flux:heading size="lg">Abschnitte</flux:heading>
                        <flux:button size="sm" icon="arrow-up-tray" wire:click="uploadNotebook" wire:loading.attr="disabled" wire:target="uploadNotebook">
                            Ganzes Notizbuch hochladen
                        </flux:button>
                    </div>
                    <flux:select wire:model.live="lightragInstance" label="LightRAG-Instanz" variant="listbox" class="max-w-xs">
                        @foreach (\Hwkdo\IntranetAppMsgraph\Support\OnenoteLightRagTarget::options() as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="flex flex-wrap gap-2">
                        @foreach($sections as $section)
                            <flux:button
                                wire:key="section-{{ $section['id'] }}"
                                wire:click="selectSection({{ \Illuminate\Support\Js::from($section['id']) }})"
                                :variant="$sectionId === $section['id'] ? 'primary' : 'filled'"
                                size="sm"
                            >
                                {{ $section['name'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($sectionId)
                <div class="flex justify-end">
                    <flux:button size="sm" icon="arrow-up-tray" wire:click="uploadSection" wire:loading.attr="disabled" wire:target="uploadSection">
                        Ganzen Abschnitt hochladen
                    </flux:button>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Seite</flux:table.column>
                        <flux:table.column>LightRAG</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($pages as $page)
                            <flux:table.row wire:key="page-{{ $page['id'] }}">
                                <flux:table.cell>{{ $page['title'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($page['status'] === 'processed')
                                        <flux:badge color="green" size="sm">{{ $page['statusLabel'] }}</flux:badge>
                                    @elseif($page['status'] === 'failed')
                                        <flux:badge color="red" size="sm">{{ $page['statusLabel'] }}</flux:badge>
                                    @elseif($page['status'] === '')
                                        <flux:badge color="zinc" size="sm">{{ $page['statusLabel'] }}</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm">{{ $page['statusLabel'] }}</flux:badge>
                                    @endif
                                    @if($page['error'])
                                        <flux:text class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $page['error'] }}</flux:text>
                                    @elseif($page['status'] === 'failed')
                                        <flux:text class="mt-1 text-xs text-red-600 dark:text-red-400">Keine Fehlerdetails. Bitte erneut hochladen.</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($page['canUpload'])
                                        <flux:button size="sm" wire:click="uploadPage({{ \Illuminate\Support\Js::from($page['id']) }})" wire:loading.attr="disabled">
                                            Hochladen
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3">Dieser Abschnitt hat keine Seiten.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </x-intranet-app-msgraph::msgraph-layout>
</div>
