<?php

use Hwkdo\IntranetAppMsgraph\Http\Controllers\OnenoteAccountConnectController;
use Hwkdo\IntranetAppMsgraph\Livewire\Auslandszugriff;
use Hwkdo\IntranetAppMsgraph\Livewire\AzureApps;
use Hwkdo\IntranetAppMsgraph\Livewire\OneNoteRag;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::middleware(['web', 'auth', 'can:see-app-msgraph'])->group(function () {
    Volt::route('apps/msgraph', 'apps.msgraph.index')->name('apps.msgraph.index');

    Volt::route('apps/msgraph/settings/user', 'apps.msgraph.settings.user')->name('apps.msgraph.settings.user');
    Volt::route('apps/msgraph/info', 'apps.msgraph.info')->name('apps.msgraph.info');

    Volt::route('apps/msgraph/entra-user', 'apps.msgraph.entra-user.index')->name('apps.msgraph.entra-user.index');

    Route::get('apps/msgraph/auslandszugriff', Auslandszugriff::class)->name('apps.msgraph.auslandszugriff.index');
});


Route::middleware(['web', 'auth', 'can:manage-app-msgraph'])->group(function () {
    Volt::route('apps/msgraph/admin', 'apps.msgraph.admin.index')->name('apps.msgraph.admin.index');

    Route::get('apps/msgraph/azure-apps', AzureApps::class)->name('apps.msgraph.azure-apps.index');

    Route::get('apps/msgraph/onenote-rag', OneNoteRag::class)->name('apps.msgraph.onenote-rag.index');

    Route::get('apps/msgraph/onenote-rag/connect', [OnenoteAccountConnectController::class, 'redirect'])
        ->name('apps.msgraph.onenote-rag.connect');
});
