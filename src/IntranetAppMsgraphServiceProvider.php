<?php

namespace Hwkdo\IntranetAppMsgraph;

use Hwkdo\IntranetAppMsgraph\Commands\SyncOnenoteLightRagStatusCommand;
use Hwkdo\IntranetAppMsgraph\Services\OnenoteDelegatedTokenService;
use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Hwkdo\IntranetAppMsgraph\Livewire\Auslandszugriff;
use Hwkdo\IntranetAppMsgraph\Livewire\AzureApps;
use Hwkdo\IntranetAppMsgraph\Livewire\OneNoteRag;
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
            ->hasCommand(SyncOnenoteLightRagStatusCommand::class)
            ->discoversMigrations();
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->bind(OnenoteDelegatedTokenInterface::class, OnenoteDelegatedTokenService::class);
        $this->app->booted(function () {
            Volt::mount(__DIR__.'/../resources/views/livewire');
            Livewire::component('apps.msgraph.auslandszugriff', Auslandszugriff::class);
            Livewire::component('apps.msgraph.azure-apps', AzureApps::class);
            Livewire::component('apps.msgraph.onenote-rag', OneNoteRag::class);
            Livewire::component('apps.msgraph.widgets.azure-app-secrets-expiring', AzureAppSecretsExpiring::class);
        });
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/channels.php');
        $this->app->resolving(Schedule::class, function (): void {
            require __DIR__.'/../routes/console.php';
        });
    }
}
