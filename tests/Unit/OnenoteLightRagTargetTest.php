<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\Support\OnenoteLightRagTarget;

beforeEach(function (): void {
    config([
        'intranet-app-msgraph.lightrag.instances' => [
            'team-meetings' => ['label' => 'Team-Meetings', 'url' => 'https://team.example'],
            'wiki' => ['label' => 'Wiki', 'url' => 'https://wiki.example'],
        ],
        'intranet-app-msgraph.lightrag.notebooks' => [
            'nb-team' => 'team-meetings',
        ],
    ]);
});

it('ordnet das teammeetings notizbuch der instanz zu', function () {
    expect(OnenoteLightRagTarget::forNotebook('nb-team', 'Irgendwas'))->toBe('team-meetings');
});

it('erkennt wiki am notizbuchnamen', function () {
    expect(OnenoteLightRagTarget::forNotebook('nb-wiki', 'Wiki Schulung'))->toBe('wiki');
});
