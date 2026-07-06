<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\LivewireAdmin;

use Flux\Flux;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsActivityFeedServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

class TeamsActivityFeedAdmin extends Component
{
    public string $search = '';

    /** @var array<int, array{id: string, upn: string, displayName: string}> */
    public array $searchResults = [];

    /** @var array{id: string, upn: string, displayName: string}|null */
    public ?array $selectedUser = null;

    public string $previewText = 'Dies ist eine Test-Benachrichtigung im Teams Activity Feed.';

    public string $actorText = 'HWKDO Intranet hat eine neue Benachrichtigung für dich.';

    public string $topicTitle = '';

    public string $webUrl = '';

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
    public function activityFeedEnabled(): bool
    {
        return app(MsGraphTeamsActivityFeedServiceInterface::class)->isEnabled();
    }

    #[Computed]
    public function teamsAppId(): ?string
    {
        $appId = config('ms-graph-laravel.teams_bot.teams_app_id');

        return is_string($appId) && $appId !== '' ? $appId : null;
    }

    #[Computed]
    public function activityType(): string
    {
        return (string) config('ms-graph-laravel.teams_activity_feed.activity_type', 'systemDefault');
    }

    #[Computed]
    public function defaultTopicTitle(): string
    {
        return (string) config('ms-graph-laravel.teams_activity_feed.topic_title', 'HWKDO Intranet');
    }

    public function sendTestNotification(): void
    {
        if ($this->selectedUser === null) {
            Flux::toast(variant: 'warning', text: 'Bitte zuerst einen Benutzer auswählen.');

            return;
        }

        $preview = trim($this->previewText);

        if ($preview === '') {
            Flux::toast(variant: 'warning', text: 'Bitte einen Vorschautext eingeben.');

            return;
        }

        try {
            app(MsGraphTeamsActivityFeedServiceInterface::class)->sendNotification(
                $this->selectedUser['id'],
                $preview,
                filled(trim($this->actorText)) ? trim($this->actorText) : null,
                filled(trim($this->topicTitle)) ? trim($this->topicTitle) : null,
                filled(trim($this->webUrl)) ? trim($this->webUrl) : null,
            );

            Flux::toast(variant: 'success', text: 'Activity-Feed-Benachrichtigung wurde in die Queue gestellt.');
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: 'Senden fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function render(): View
    {
        return view('intranet-app-msgraph::livewire.teams-activity-feed-admin');
    }
}
