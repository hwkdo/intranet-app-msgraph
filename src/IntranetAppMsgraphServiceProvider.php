<?php

namespace Hwkdo\IntranetAppMsgraph;

use Hwkdo\IntranetAppMsgraph\Jobs\RemoveExpiredAuslandseinsatzMemberships;
use Hwkdo\IntranetAppMsgraph\Livewire\Auslandszugriff;
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
        // Gate::policy(Raum::class, RaumPolicy::class);
        $this->app->booted(function () {
            Volt::mount(__DIR__.'/../resources/views/livewire');
            Livewire::component('apps.msgraph.auslandszugriff', Auslandszugriff::class);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->job(RemoveExpiredAuslandseinsatzMemberships::class)->dailyAt('02:00');
            });
        });
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/console.php');
    }
}
