<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth', 'can:see-app-msgraph'])->group(function () {
    Volt::route('apps/msgraph', 'apps.msgraph.index')->name('apps.msgraph.index');
    Volt::route('apps/msgraph/example', 'apps.msgraph.example')->name('apps.msgraph.example');
    Volt::route('apps/msgraph/settings/user', 'apps.msgraph.settings.user')->name('apps.msgraph.settings.user');
});

Route::middleware(['web', 'auth', 'can:manage-app-msgraph'])->group(function () {
    Volt::route('apps/msgraph/admin', 'apps.msgraph.admin.index')->name('apps.msgraph.admin.index');
});
