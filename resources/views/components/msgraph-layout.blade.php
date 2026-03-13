@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $canAccessAuslandszugriff = auth()->user()?->can('manage-app-msgraph')
        || auth()->user()?->can('manage-app-msgraph-lehrgangsverwaltung');

    $defaultNavItems = array_values(array_filter([
        ['label' => 'Übersicht', 'href' => route('apps.msgraph.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Entra-User', 'href' => route('apps.msgraph.entra-user.index'), 'icon' => 'user', 'description' => 'Entra-User anzeigen', 'buttonText' => 'Entra-User öffnen'],
        $canAccessAuslandszugriff ? ['label' => 'Auslandszugriff', 'href' => route('apps.msgraph.auslandszugriff.index'), 'icon' => 'globe-alt', 'description' => 'Auslandseinsatz-Gruppe verwalten', 'buttonText' => 'Auslandszugriff öffnen'] : null,
        ['label' => 'Meine Einstellungen', 'href' => route('apps.msgraph.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen anpassen', 'buttonText' => 'Einstellungen öffnen'],
        auth()->user()?->can('manage-app-msgraph') ? ['label' => 'Azure Apps', 'href' => route('apps.msgraph.azure-apps.index'), 'icon' => 'key', 'description' => 'Azure App-Registrierungen und Secret-Ablauf', 'buttonText' => 'Azure Apps öffnen'] : null,
        ['label' => 'Admin', 'href' => route('apps.msgraph.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-msgraph'],
    ]));

    $navItems = !empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('msgraph');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

@if(request()->routeIs('apps.msgraph.index'))
    <x-intranet-app-base::app-layout 
        app-identifier="msgraph"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="false"
    >
        <x-intranet-app-base::app-index-auto 
            app-identifier="msgraph"
            app-name="Msgraph App"
            app-description="Verwaltung der MS Graph Resourcen"
            :nav-items="$navItems"
            welcome-title="Willkommen zur Msgraph App"
            welcome-description="Dies ist eine Beispiel-App, die als Msgraph für neue Intranet-Apps dient."
        />
    </x-intranet-app-base::app-layout>
@else
    <x-intranet-app-base::app-layout 
        app-identifier="msgraph"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="true"
    >
        {{ $slot }}
    </x-intranet-app-base::app-layout>
@endif
