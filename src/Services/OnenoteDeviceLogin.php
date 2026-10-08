<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class OnenoteDeviceLogin
{
    private const CACHE_KEY = 'msgraph-onenote-device-code';

    public function __construct(
        private OnenoteDelegatedTokenService $tokens,
    ) {}

    /**
     * @return array{userCode: string, verificationUrl: string, message: string}
     */
    public function start(): array
    {
        $tenant = (string) config('services.microsoft.tenant', 'hwkdoedu.onmicrosoft.com');
        $response = Http::asForm()
            ->timeout(15)
            ->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/devicecode', [
                'client_id' => config('services.microsoft.client_id'),
                'scope' => implode(' ', [
                    'openid',
                    'profile',
                    'offline_access',
                    'User.Read',
                    'Notes.Read.All',
                ]),
            ]);

        /** @var array{device_code?: mixed, user_code?: mixed, verification_uri?: mixed, message?: mixed, expires_in?: mixed, error?: mixed, error_description?: mixed} $payload */
        $payload = $response->json() ?? [];
        $deviceCode = $payload['device_code'] ?? null;
        $userCode = $payload['user_code'] ?? null;
        $verificationUrl = $payload['verification_uri'] ?? null;
        if (! $response->successful() || ! is_string($deviceCode) || $deviceCode === '' || ! is_string($userCode) || ! is_string($verificationUrl)) {
            throw new InvalidArgumentException($this->publicClientHint($payload));
        }

        Cache::put(self::CACHE_KEY, $deviceCode, now()->addSeconds(max((int) ($payload['expires_in'] ?? 900), 60)));

        return [
            'userCode' => $userCode,
            'verificationUrl' => $verificationUrl,
            'message' => is_string($payload['message'] ?? null) ? $payload['message'] : 'Mit dem OneNote-Konto auf der Microsoft-Seite anmelden.',
        ];
    }

    /**
     * @return 'pending'|'connected'
     */
    public function poll(): string
    {
        $deviceCode = Cache::get(self::CACHE_KEY);
        if (! is_string($deviceCode) || $deviceCode === '') {
            throw new InvalidArgumentException('Die Anmeldung ist abgelaufen. Bitte neu starten.');
        }

        $tenant = (string) config('services.microsoft.tenant', 'hwkdoedu.onmicrosoft.com');
        $response = Http::asForm()
            ->timeout(15)
            ->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                'device_code' => $deviceCode,
            ]);

        /** @var array{error?: mixed, error_description?: mixed, access_token?: mixed, refresh_token?: mixed, expires_in?: mixed, scope?: mixed} $payload */
        $payload = $response->json() ?? [];
        $error = $payload['error'] ?? null;
        if ($error === 'authorization_pending' || $error === 'slow_down') {
            return 'pending';
        }

        if (is_string($error) && $error !== '') {
            Cache::forget(self::CACHE_KEY);

            throw new InvalidArgumentException($this->tokenError($error, (string) ($payload['error_description'] ?? '')));
        }

        $accessToken = $payload['access_token'] ?? null;
        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            Cache::forget(self::CACHE_KEY);

            throw new InvalidArgumentException('Die Microsoft-Anmeldung ist fehlgeschlagen.');
        }

        $scopes = $payload['scope'] ?? [];
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes)) ?: [];
        }

        $refreshToken = $payload['refresh_token'] ?? null;
        $this->tokens->store(
            $accessToken,
            is_string($refreshToken) ? $refreshToken : null,
            (int) ($payload['expires_in'] ?? 3600),
            is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
            $this->upn($accessToken),
        );
        Cache::forget(self::CACHE_KEY);

        return 'connected';
    }

    public function pending(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    private function upn(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get('https://graph.microsoft.com/v1.0/me', [
                '$select' => 'userPrincipalName',
            ]);

        $upn = $response->json('userPrincipalName');

        return is_string($upn) && $upn !== '' ? $upn : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publicClientHint(array $payload): string
    {
        $description = (string) ($payload['error_description'] ?? $payload['error'] ?? '');
        if (str_contains($description, '7000218') || str_contains($description, 'AADSTS70002')) {
            return 'An der Entra-App muss „Öffentliche Clientflows zulassen“ aktiv sein.';
        }

        return 'Der Gerätecode für die Microsoft-Anmeldung konnte nicht gestartet werden.';
    }

    private function tokenError(string $error, string $description): string
    {
        if ($error === 'expired_token') {
            return 'Der Code ist abgelaufen. Bitte die Anmeldung neu starten.';
        }

        if ($error === 'authorization_declined' || $error === 'access_denied') {
            return 'Die Anmeldung wurde abgebrochen.';
        }

        if (str_contains($description, '7000218') || str_contains($description, 'AADSTS70002')) {
            return 'An der Entra-App muss „Öffentliche Clientflows zulassen“ aktiv sein.';
        }

        return 'Die Microsoft-Anmeldung ist fehlgeschlagen.';
    }
}
