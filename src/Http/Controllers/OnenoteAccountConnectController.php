<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Http\Controllers;

use Hwkdo\IntranetAppMsgraph\Services\OnenoteDelegatedTokenService;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class OnenoteAccountConnectController
{
    public function redirect(): SymfonyRedirectResponse|RedirectResponse
    {
        session([OnenoteDelegatedTokenService::CONNECT_SESSION_KEY => true]);

        return Socialite::driver('microsoft')
            ->setScopes([
                'openid',
                'profile',
                'offline_access',
                'User.Read',
                'Notes.Read.All',
            ])
            ->with([
                'prompt' => 'select_account',
                'login_hint' => 'filer@hwkdoedu.onmicrosoft.com',
            ])
            ->redirect();
    }
}
