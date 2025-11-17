<?php

use Flux\Flux;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Hwkdo\MsGraphLaravel\Models\Subscription;
use Hwkdo\MsGraphLaravel\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use function Livewire\Volt\{computed, state, title};

title('Msgraph - Admin');

state([
    'activeTab' => 'einstellungen',
    'selectedMapping' => null,
    'subscriptionPendingDeleteId' => null,
]);

$subscriptions = computed(fn() => Subscription::orderBy('created_at', 'desc')->get());
$mappings = computed(fn() => GraphWebhookJobMapping::orderBy('created_at', 'desc')->get());

$showMappingDetails = function ($id) {
    $this->selectedMapping = GraphWebhookJobMapping::find($id);
    Flux::modal('mapping-details-modal')->show();

};

$confirmSubscriptionDelete = function (int $subscriptionId) {
    $subscription = $this->subscriptions->firstWhere('id', $subscriptionId);

    if (! $subscription) {
        Flux::toast(
            variant: 'danger',
            text: 'Subscription wurde nicht gefunden.'
        );

        return;
    }

    $this->subscriptionPendingDeleteId = $subscriptionId;
    Flux::modal('subscription-delete-modal')->show();
};

$deleteSubscription = function () {
    if (! $this->subscriptionPendingDeleteId) {
        return;
    }

    $subscription = Subscription::find($this->subscriptionPendingDeleteId);

    if (! $subscription) {
        Flux::toast(
            variant: 'danger',
            text: 'Subscription ist bereits entfernt worden.'
        );

        $this->subscriptionPendingDeleteId = null;
        Flux::modal('subscription-delete-modal')->close();

        return;
    }

    try {
        $result = app(SubscriptionService::class)->unsubscribe($subscription->graph_id);

        if (! $result) {
            Flux::toast(
                variant: 'danger',
                text: 'Subscription konnte nicht gefunden werden.'
            );
        } else {
            Flux::toast(
                variant: 'success',
                text: 'Subscription wurde gelöscht.'
            );
        }
    } catch (\Throwable $exception) {
        Log::error('Msgraph Admin - Fehler beim Löschen der Subscription', [
            'subscription_id' => $subscription->id,
            'graph_id' => $subscription->graph_id,
            'message' => $exception->getMessage(),
        ]);

        Flux::toast(
            variant: 'danger',
            text: 'Subscription konnte nicht gelöscht werden.'
        );
    }

    $this->subscriptionPendingDeleteId = null;
    Flux::modal('subscription-delete-modal')->close();
    unset($this->subscriptions);
};

$checkSubscriptions = function () {
    try {
        $result = app(SubscriptionService::class)->checkAndSyncSubscriptions();

        if ($result) {
            Flux::toast(
                variant: 'success',
                text: 'Subscriptions wurden erfolgreich geprüft und synchronisiert.'
            );
            unset($this->subscriptions);
        } else {
            Flux::toast(
                variant: 'danger',
                text: 'Fehler beim Prüfen der Subscriptions.'
            );
        }
    } catch (\Throwable $exception) {
        Log::error('Msgraph Admin - Fehler beim Prüfen der Subscriptions', [
            'message' => $exception->getMessage(),
        ]);

        Flux::toast(
            variant: 'danger',
            text: 'Subscriptions konnten nicht geprüft werden.'
        );
    }
};

?>
<div>
<x-intranet-app-msgraph::msgraph-layout heading="Msgraph App" subheading="Admin">
    <flux:tab.group>
        <flux:tabs wire:model="activeTab">
            <flux:tab name="einstellungen" icon="cog-6-tooth">Einstellungen</flux:tab>
            <flux:tab name="statistiken" icon="chart-bar">Statistiken</flux:tab>
            <flux:tab name="subscriptions" icon="bell">Registered Subscriptions</flux:tab>
            <flux:tab name="mappings" icon="link">Mappings</flux:tab>
        </flux:tabs>
        
        <flux:tab.panel name="einstellungen">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::admin-settings', [
                    'appIdentifier' => 'msgraph',
                    'settingsModelClass' => '\Hwkdo\IntranetAppMsgraph\Models\IntranetAppmsgraphSettings',
                    'appSettingsClass' => '\Hwkdo\IntranetAppMsgraph\Data\AppSettings'
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="statistiken">
            <div style="min-height: 400px;">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">App-Statistiken</flux:heading>
                    <flux:text class="mb-6">
                        Übersicht über die Nutzung der Msgraph App.
                    </flux:text>
                    
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Aktive Benutzer</flux:heading>
                            <flux:text size="xl" class="mt-2">42</flux:text>
                        </div>
                        
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Seitenaufrufe</flux:heading>
                            <flux:text size="xl" class="mt-2">1,234</flux:text>
                        </div>
                        
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Letzte Aktivität</flux:heading>
                            <flux:text size="xl" class="mt-2">2 Min</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="subscriptions">
            <div style="min-height: 400px;">
                <flux:card>
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">Registered Subscriptions</flux:heading>
                        <flux:button
                            wire:click="checkSubscriptions"
                            wire:target="checkSubscriptions"
                            wire:loading.attr="disabled"
                            icon="arrow-path"
                            variant="primary"
                        >
                            <span wire:loading.remove wire:target="checkSubscriptions">Check Subscriptions</span>
                            <span wire:loading wire:target="checkSubscriptions">Prüfe...</span>
                        </flux:button>
                    </div>
                    <flux:text class="mb-6">
                        Übersicht über alle registrierten MS Graph Subscriptions.
                    </flux:text>
                    
                    @if($this->subscriptions->isEmpty())
                        <div class="rounded-lg border p-8 text-center">
                            <flux:icon.bell class="mx-auto mb-4 size-12 text-zinc-400" />
                            <flux:heading size="md" class="mb-2">Keine Subscriptions vorhanden</flux:heading>
                            <flux:text>Es sind derzeit keine MS Graph Subscriptions registriert.</flux:text>
                        </div>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>ID</flux:table.column>
                                <flux:table.column>Graph ID</flux:table.column>
                                <flux:table.column>Resource</flux:table.column>
                                <flux:table.column>Notification URL</flux:table.column>
                                <flux:table.column>Expiration</flux:table.column>
                                <flux:table.column>Created At</flux:table.column>
                                <flux:table.column>Updated At</flux:table.column>
                                <flux:table.column>Aktionen</flux:table.column>
                            </flux:table.columns>
                            
                            <flux:table.rows>
                                @foreach($this->subscriptions as $subscription)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $subscription->id }}</flux:table.cell>
                                        <flux:table.cell>{{ $subscription->graph_id }}</flux:table.cell>
                                        <flux:table.cell>{{ $subscription->resource }}</flux:table.cell>
                                        <flux:table.cell class="max-w-xs truncate" title="{{ $subscription->notificationUrl }}">
                                            {{ $subscription->notificationUrl }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            {{ $subscription->expiration?->format('d.m.Y H:i:s') ?? '-' }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            {{ $subscription->created_at?->format('d.m.Y H:i:s') ?? '-' }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            {{ $subscription->updated_at?->format('d.m.Y H:i:s') ?? '-' }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button
                                                wire:click="confirmSubscriptionDelete({{ $subscription->id }})"
                                                size="xs"
                                                icon="trash"
                                                variant="danger"
                                            >
                                                Löschen
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </flux:card>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="mappings">
            <div style="min-height: 400px;">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Webhook Job Mappings</flux:heading>
                    <flux:text class="mb-6">
                        Übersicht über alle Webhook-zu-Job Mappings für MS Graph Webhooks.
                    </flux:text>
                    
                    @if($this->mappings->isEmpty())
                        <div class="rounded-lg border p-8 text-center">
                            <flux:icon.link class="mx-auto mb-4 size-12 text-zinc-400" />
                            <flux:heading size="md" class="mb-2">Keine Mappings vorhanden</flux:heading>
                            <flux:text>Es sind derzeit keine Webhook Job Mappings konfiguriert.</flux:text>
                        </div>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>ID</flux:table.column>
                                <flux:table.column>Name</flux:table.column>
                                <flux:table.column>Webhook Type</flux:table.column>
                                <flux:table.column>Job Class</flux:table.column>
                                <flux:table.column>Is Active</flux:table.column>
                                <flux:table.column>Created At</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.columns>
                            
                            <flux:table.rows>
                                @foreach($this->mappings as $mapping)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $mapping->id }}</flux:table.cell>
                                        <flux:table.cell>{{ $mapping->name ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>{{ $mapping->webhook_type }}</flux:table.cell>
                                        <flux:table.cell class="max-w-xs truncate" title="{{ $mapping->job_class }}">
                                            {{ class_basename($mapping->job_class) }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if($mapping->is_active)
                                                <flux:badge color="green" size="sm">Aktiv</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm">Inaktiv</flux:badge>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            {{ $mapping->created_at?->format('d.m.Y H:i:s') ?? '-' }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button
                                                wire:click="showMappingDetails({{ $mapping->id }})"
                                                size="xs"
                                                icon="eye"
                                                variant="ghost"
                                            >
                                                Details
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </flux:card>
            </div>            
        </flux:tab.panel>
    </flux:tab.group>    
</x-intranet-app-msgraph::msgraph-layout>

<flux:modal name="subscription-delete-modal" class="max-w-lg">
    @php
        $subscriptionPendingDelete = $this->subscriptionPendingDeleteId
            ? $this->subscriptions->firstWhere('id', $this->subscriptionPendingDeleteId)
            : null;
    @endphp

    @if($subscriptionPendingDelete)
        <div class="space-y-4">
            <flux:heading size="lg">Subscription löschen</flux:heading>
            <flux:text>
                Möchten Sie die Subscription mit der Graph-ID
                <span class="font-semibold">{{ $subscriptionPendingDelete->graph_id }}</span>
                wirklich löschen? Dies entfernt auch den lokalen Datensatz.
            </flux:text>

            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/40 dark:text-red-200">
                Dieser Vorgang kann nicht rückgängig gemacht werden.
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('subscription-delete-modal').close()">
                    Abbrechen
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteSubscription"
                    wire:target="deleteSubscription"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="deleteSubscription">Ja, löschen</span>
                    <span wire:loading wire:target="deleteSubscription">Lösche...</span>
                </flux:button>
            </div>
        </div>
    @endif
</flux:modal>

<flux:modal name="mapping-details-modal" class="max-w-3xl">
                @if($selectedMapping)
                    <div class="space-y-6">
                        <flux:heading size="lg">Mapping Details: {{ $selectedMapping->name ?? $selectedMapping->webhook_type }}</flux:heading>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">ID</flux:text>
                                <flux:text>{{ $selectedMapping->id }}</flux:text>
                            </div>
                            
                            <div>
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Name</flux:text>
                                <flux:text>{{ $selectedMapping->name ?? '-' }}</flux:text>
                            </div>
                            
                            <div>
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Webhook Type</flux:text>
                                <flux:text>{{ $selectedMapping->webhook_type }}</flux:text>
                            </div>
                            
                            <div>
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Status</flux:text>
                                <div>
                                    @if($selectedMapping->is_active)
                                        <flux:badge color="green">Aktiv</flux:badge>
                                    @else
                                        <flux:badge color="zinc">Inaktiv</flux:badge>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="md:col-span-2">
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Job Class</flux:text>
                                <flux:text class="break-all font-mono text-sm">{{ $selectedMapping->job_class }}</flux:text>
                            </div>
                            
                            @if($selectedMapping->description)
                                <div class="md:col-span-2">
                                    <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Description</flux:text>
                                    <flux:text class="break-all">{{ $selectedMapping->description }}</flux:text>
                                </div>
                            @endif
                            
                            @if($selectedMapping->filepath)
                            <div class="md:col-span-2">
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Filepath</flux:text>
                                <flux:text class="break-all font-mono text-sm">{{ $selectedMapping->filepath }}</flux:text>
                            </div>
                            @endif
                            
                            @if($selectedMapping->upn)
                                <div class="md:col-span-2">
                                    <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">UPN</flux:text>
                                    <flux:text class="break-all">{{ $selectedMapping->upn }}</flux:text>
                                </div>
                            @endif
                            
                            @if($selectedMapping->resource)
                                <div class="md:col-span-2">
                                    <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Resource</flux:text>
                                    <flux:text class="break-all">{{ $selectedMapping->resource }}</flux:text>
                                </div>
                            @endif
                            
                            @if($selectedMapping->notification_url)
                                <div class="md:col-span-2">
                                    <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Notification URL</flux:text>
                                    <flux:text class="break-all">{{ $selectedMapping->notification_url }}</flux:text>
                                </div>
                            @endif
                            
                            @if($selectedMapping->change_type)
                                <div>
                                    <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Change Type</flux:text>
                                    <flux:text>{{ $selectedMapping->change_type }}</flux:text>
                                </div>
                            @endif
                            
                            <div>
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Created At</flux:text>
                                <flux:text>{{ $selectedMapping->created_at?->format('d.m.Y H:i:s') ?? '-' }}</flux:text>
                            </div>
                            
                            <div>
                                <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">Updated At</flux:text>
                                <flux:text>{{ $selectedMapping->updated_at?->format('d.m.Y H:i:s') ?? '-' }}</flux:text>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-2">
                            <flux:button variant="ghost" x-on:click="$flux.modal('mapping-details-modal').close()">
                                Schließen
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:modal>
                </div>