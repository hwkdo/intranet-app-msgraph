<?php

use function Livewire\Volt\{title};

title('Msgraph - App-Info');

?>

<x-intranet-app-msgraph::msgraph-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    @livewire('intranet-app-base::app-info', ['appIdentifier' => 'msgraph'])
</x-intranet-app-msgraph::msgraph-layout>
