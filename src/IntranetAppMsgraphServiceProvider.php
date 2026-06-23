<?php

namespace Hwkdo\IntranetAppMsgraph;

use Hwkdo\IntranetAppMsgraph\Livewire\Auslandszugriff;
use Hwkdo\IntranetAppMsgraph\Livewire\AzureApps;
use Hwkdo\IntranetAppMsgraph\Livewire\DashboardWidgets\AzureAppSecretsExpiring;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppMsgraphServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('intranet-app-msgraph')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations();
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->booted(function () {
            Volt::mount(__DIR__.'/../resources/views/livewire');
            Livewire::component('apps.msgraph.auslandszugriff', Auslandszugriff::class);
            Livewire::component('apps.msgraph.azure-apps', AzureApps::class);
            Livewire::component('apps.msgraph.widgets.azure-app-secrets-expiring', AzureAppSecretsExpiring::class);
        });
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->app->resolving(Schedule::class, function (): void {
            require __DIR__.'/../routes/console.php';
        });
    }
}
