<?php

use function Livewire\Volt\{state, title};

title('Msgraph - Beispiel');

state(['exampleData' => [
    'name' => 'Beispiel Item',
    'description' => 'Dies ist ein Beispiel-Item für die Msgraph App',
    'status' => 'active',
    'created_at' => now()->format('d.m.Y H:i'),
]]);

?>

<x-intranet-app-msgraph::msgraph-layout heading="Beispiel-Seite" subheading="Demonstration der Msgraph-Funktionalität">
    <flux:card class="glass-card">
        <flux:heading size="lg" class="mb-4">Beispiel-Content</flux:heading>
        <flux:text class="mb-6">
            Diese Seite zeigt, wie eine typische App-Seite aussehen könnte.
        </flux:text>
        
        <div class="space-y-4">
            <div class="rounded-xl border border-[#d0e3f9]/80 dark:border-white/10 bg-[#d0e3f9]/40 dark:bg-[#073070]/40 p-4">
                <flux:heading size="md">{{ $exampleData['name'] }}</flux:heading>
                <flux:text class="mt-2">{{ $exampleData['description'] }}</flux:text>
                <div class="mt-2 flex items-center gap-2">
                    <flux:badge variant="success">{{ $exampleData['status'] }}</flux:badge>
                    <flux:text class="text-sm text-slate-500 dark:text-white/50">{{ $exampleData['created_at'] }}</flux:text>
                </div>
            </div>
            
            <div class="rounded-xl border border-[#d0e3f9]/80 dark:border-white/10 bg-[#d0e3f9]/40 dark:bg-[#073070]/40 p-4">
                <flux:heading size="md">Weitere Informationen</flux:heading>
                <flux:text class="mt-2">
                    Hier könnten weitere Details oder Aktionen für das Beispiel-Item angezeigt werden.
                </flux:text>
                <div class="mt-4 flex gap-2">
                    <flux:button variant="primary" size="sm">Bearbeiten</flux:button>
                    <flux:button variant="outline" size="sm">Löschen</flux:button>
                </div>
            </div>
        </div>
    </flux:card>
</x-intranet-app-msgraph::msgraph-layout>
