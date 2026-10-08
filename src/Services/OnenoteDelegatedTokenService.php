<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

use Hwkdo\IntranetAppMsgraph\Models\OnenoteDelegatedAccount;
use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class OnenoteDelegatedTokenService implements OnenoteDelegatedTokenInterface
{
    public const CONNECT_SESSION_KEY = 'onenote_account_connect';

    public function connectedUpn(): ?string
    {
        $upn = OnenoteDelegatedAccount::query()->where('account_key', OnenoteDelegatedAccount::KEY)->value('upn');

        return is_string($upn) && $upn !== '' ? $upn : null;
    }

    public function accessToken(): string
    {
        $account = OnenoteDelegatedAccount::query()->where('account_key', OnenoteDelegatedAccount::KEY)->first();
        if ($account === null) {
            throw new InvalidArgumentException('Für OneNote ist noch kein Microsoft-Konto verbunden.');
        }

        if ($account->expires_at !== null && $account->expires_at->isAfter(now()->addSeconds(120))) {
            return (string) $account->access_token;
        }

        return $this->refresh($account);
    }

    /**
     * @param  list<string>  $scopes
     */
    public function store(string $accessToken, ?string $refreshToken, int $expiresIn, array $scopes, ?string $upn): void
    {
        OnenoteDelegatedAccount::query()->updateOrCreate(
            ['account_key' => OnenoteDelegatedAccount::KEY],
            [
                'upn' => $upn,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds(max($expiresIn, 0)),
                'scopes' => $scopes,
            ],
        );
    }

    private function refresh(OnenoteDelegatedAccount $account): string
    {
        $refreshToken = $account->refresh_token;
        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new InvalidArgumentException('Das OneNote-Konto muss neu verbunden werden.');
        }

        $tenant = (string) config('services.microsoft.tenant', 'hwkdoedu.onmicrosoft.com');

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'scope' => implode(' ', $this->scopes()),
                ])
                ->throw();
        } catch (RequestException|Throwable) {
            throw new InvalidArgumentException('Das OneNote-Konto konnte nicht erneuert werden. Bitte neu verbinden.');
        }

        /** @var array{access_token?: mixed, refresh_token?: mixed, expires_in?: mixed, scope?: mixed} $payload */
        $payload = $response->json() ?? [];
        $accessToken = $payload['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new InvalidArgumentException('Das OneNote-Konto konnte nicht erneuert werden. Bitte neu verbinden.');
        }

        $newRefresh = $payload['refresh_token'] ?? null;
        $granted = $payload['scope'] ?? $account->scopes;
        if (is_string($granted)) {
            $granted = preg_split('/\s+/', trim($granted)) ?: [];
        }

        $account->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => is_string($newRefresh) && $newRefresh !== '' ? $newRefresh : $refreshToken,
            'expires_at' => now()->addSeconds(max((int) ($payload['expires_in'] ?? 3600), 0)),
            'scopes' => is_array($granted) ? array_values(array_filter($granted, 'is_string')) : [],
        ])->save();

        return $accessToken;
    }

    /**
     * @return list<string>
     */
    private function scopes(): array
    {
        return [
            'openid',
            'profile',
            'offline_access',
            'User.Read',
            'Notes.Read.All',
        ];
    }
}
