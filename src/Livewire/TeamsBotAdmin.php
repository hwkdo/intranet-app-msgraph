<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Livewire;

use Flux\Flux;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

class TeamsBotAdmin extends Component
{
    public string $search = '';

    /** @var array<int, array{id: string, upn: string, displayName: string}> */
    public array $searchResults = [];

    /** @var array{id: string, upn: string, displayName: string}|null */
    public ?array $selectedUser = null;

    public string $testMessage = 'Dies ist eine Testnachricht vom HWK Intranet Teams-Bot.';

    public function updatedSearch(): void
    {
        if (strlen(trim($this->search)) < 2) {
            $this->searchResults = [];

            return;
        }

        $userService = app(MsGraphUserServiceInterface::class);
        $result = $userService->getUsersPaginated(20, trim($this->search));
        $users = $result['users'] ?? [];

        $this->searchResults = collect($users)
            ->map(fn ($user): array => [
                'id' => (string) $user->getId(),
                'upn' => (string) $user->getUserPrincipalName(),
                'displayName' => (string) ($user->getDisplayName() ?? ''),
            ])
            ->filter(fn (array $user): bool => $user['id'] !== '' && $user['upn'] !== '')
            ->values()
            ->all();
    }

    public function selectUser(string $upn, string $displayName, string $id): void
    {
        $this->selectedUser = [
            'id' => $id,
            'upn' => $upn,
            'displayName' => $displayName,
        ];
        $this->search = $displayName !== '' ? $displayName : $upn;
        $this->searchResults = [];
    }

    public function clearSelectedUser(): void
    {
        $this->selectedUser = null;
        $this->search = '';
        $this->searchResults = [];
    }

    #[Computed]
    public function botEnabled(): bool
    {
        return app(MsGraphTeamsBotServiceInterface::class)->isEnabled();
    }

    #[Computed]
    public function botAppId(): ?string
    {
        $appId = config('ms-graph-laravel.teams_bot.app_id');

        return is_string($appId) && $appId !== '' ? $appId : null;
    }

    #[Computed]
    public function activeConversationCount(): int
    {
        return app(MsGraphTeamsBotServiceInterface::class)->activeConversationCount();
    }

    /**
     * @return array{healthy: bool, service: string|null, base_url: string}
     */
    #[Computed]
    public function sdkRestHealth(): array
    {
        return app(MsGraphTeamsBotServiceInterface::class)->getSdkRestHealthStatus();
    }

    #[Computed]
    public function sdkRestConfigured(): bool
    {
        return filled(config('ms-graph-laravel.teams_sdk_rest.base_url'));
    }

    public function refreshSdkHealth(): void
    {
        unset($this->sdkRestHealth);

        $health = $this->sdkRestHealth;

        if ($health['healthy']) {
            Flux::toast(variant: 'success', text: 'teams-sdk-rest ist erreichbar.');

            return;
        }

        Flux::toast(
            variant: 'danger',
            text: 'teams-sdk-rest ist nicht erreichbar. Bitte Container und TEAMS_SDK_REST_URL prüfen.',
        );
    }

    /**
     * @return Collection<int, TeamsBotConversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return app(MsGraphTeamsBotServiceInterface::class)->listConversations();
    }

    public function installBot(): void
    {
        if ($this->selectedUser === null) {
            Flux::toast(variant: 'warning', text: 'Bitte zuerst einen Benutzer auswählen.');

            return;
        }

        try {
            app(MsGraphTeamsBotServiceInterface::class)->installForUser(
                $this->selectedUser['id'],
                $this->selectedUser['upn'],
                $this->selectedUser['displayName'] !== '' ? $this->selectedUser['displayName'] : null,
            );

            Flux::toast(
                variant: 'success',
                text: 'Bot-Installation wurde gestartet. Bei Erfolg wird der Status automatisch aktualisiert.'
            );

            unset($this->conversations);
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: 'Installation fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function sendTestMessage(): void
    {
        if ($this->selectedUser === null) {
            Flux::toast(variant: 'warning', text: 'Bitte zuerst einen Benutzer auswählen.');

            return;
        }

        $message = trim($this->testMessage);

        if ($message === '') {
            Flux::toast(variant: 'warning', text: 'Bitte eine Testnachricht eingeben.');

            return;
        }

        try {
            app(MsGraphTeamsBotServiceInterface::class)->sendMessage(
                $this->selectedUser['id'],
                $message,
            );

            Flux::toast(variant: 'success', text: 'Testnachricht wurde in die Queue gestellt.');

            unset($this->conversations);
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: 'Testnachricht fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function installAllUsers(): void
    {
        try {
            Artisan::call('ms-graph:teams-bot-install-all');

            Flux::toast(
                variant: 'success',
                text: 'Masseninstallation gestartet. Details siehe Queue/Logs.'
            );
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: 'Masseninstallation fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.teams-bot-admin');
    }
}
