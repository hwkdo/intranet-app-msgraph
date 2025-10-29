<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth', 'can:see-app-msgraph'])->group(function () {
    Volt::route('apps/msgraph', 'apps.msgraph.index')->name('apps.msgraph.index');
    Volt::route('apps/msgraph/settings/user', 'apps.msgraph.settings.user')->name('apps.msgraph.settings.user');
    Volt::route('apps/msgraph/entra-user', 'apps.msgraph.entra-user.index')->name('apps.msgraph.entra-user.index');
});

Route::middleware(['web', 'auth', 'can:manage-app-msgraph'])->group(function () {
    Volt::route('apps/msgraph/admin', 'apps.msgraph.admin.index')->name('apps.msgraph.admin.index');
});
