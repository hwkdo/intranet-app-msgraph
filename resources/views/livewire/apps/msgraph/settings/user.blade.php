<?php

use function Livewire\Volt\{title};

title('Msgraph - Meine Einstellungen');

?>

<x-intranet-app-msgraph::msgraph-layout heading="Meine Einstellungen" subheading="Persönliche Einstellungen für die Msgraph App">
    @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'msgraph'])
</x-intranet-app-msgraph::msgraph-layout>
