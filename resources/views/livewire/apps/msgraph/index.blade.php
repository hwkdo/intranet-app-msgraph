<?php

use Hwkdo\IntranetAppBase\Livewire\Concerns\InteractsWithAppDashboard;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Msgraph Dashboard')] class extends Component
{
    use InteractsWithAppDashboard;

    public function mount(DashboardWidgetRegistry $registry): void
    {
        $this->initializeAppDashboard($this->dashboardAppIdentifier(), $registry);
    }

    protected function dashboardAppIdentifier(): string
    {
        return 'msgraph';
    }

    protected function dashboardSyncEventName(): string
    {
        return 'msgraph-dashboard-sync';
    }
};
?>

<div>
    <x-intranet-app-msgraph::msgraph-layout
        heading="Msgraph Dashboard"
        subheading="Individuelle Startseite für die Msgraph-App"
        :render-app-index-auto="false"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:text class="flex-1 text-zinc-500 dark:text-white">Widgets auswählen und per Drag &amp; Drop anordnen.</flux:text>
                <div class="ml-auto shrink-0">
                    @include('intranet-app-base::dashboard.widgets-flyout', [
                        'modalName' => 'msgraph-dashboard-widgets-flyout',
                        'sections' => [
                            ['label' => 'Msgraph', 'widgets' => $this->availableWidgets],
                        ],
                        'enabledWidgets' => $enabledWidgets,
                    ])
                </div>
            </div>

            @include('intranet-app-base::dashboard.grid-container', [
                'gridElementId' => 'msgraph-dashboard-grid',
                'gridWireKeyPrefix' => 'msgraph-dashboard-grid',
                'itemWireKeyPrefix' => 'msgraph-dashboard-item',
                'widgetKeyPrefix' => 'msgraph-dashboard-widget',
                'enabledWidgets' => $enabledWidgets,
                'layout' => $layout,
                'widgets' => $this->enabledWidgetDefinitions(),
                'widgetRenderVersion' => $widgetRenderVersion,
            ])
        </div>
    </x-intranet-app-msgraph::msgraph-layout>

    @include('intranet-app-base::dashboard.grid-stack-assets')

    @script
        @include('intranet-app-base::dashboard.grid-stack-init', [
            'gridElementId' => 'msgraph-dashboard-grid',
            'syncEventName' => 'msgraph-dashboard-sync',
            'saveMethod' => 'saveLayout',
        ])
    @endscript
</div>

@push('app-styles')
    <style>
        #msgraph-dashboard-grid .grid-stack-item-content {
            background: transparent;
            overflow: hidden;
        }
    </style>
@endpush