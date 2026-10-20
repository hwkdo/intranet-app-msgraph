<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Msgraph');

?>

<div>
    <x-intranet-app-msgraph::msgraph-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für die Msgraph-App">
        @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'msgraph'])
    </x-intranet-app-msgraph::msgraph-layout>
</div>
