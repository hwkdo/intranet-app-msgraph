<?php

// config for Hwkdo/IntranetAppmsgraph
return [
    'roles' => [
        'admin' => [
            'name' => 'App-Msgraph-Admin',
            'permissions' => [
                'see-app-msgraph',
                'manage-app-msgraph',
            ],
        ],
        'user' => [
            'name' => 'App-Msgraph-Benutzer',
            'permissions' => [
                'see-app-msgraph',
            ],
        ],
        'lehrgangsverwaltunguser' => [
            'name' => 'App-Msgraph-Benutzer-Lehrgangsverwaltung',
            'permissions' => [
                'see-app-msgraph',
                'manage-app-msgraph-lehrgangsverwaltung',
            ],
        ],
    ],

    'lightrag' => [
        'api_key' => env('LIGHTRAG_API_KEY'),
        'instances' => [
            'team-meetings' => [
                'label' => 'Team-Meetings',
                'url' => env('LIGHTRAG_TEAM_MEETINGS_URL', 'https://lightrag-team-meetings.swarm.hwkdo.com'),
            ],
            'wiki' => [
                'label' => 'Wiki',
                'url' => env('LIGHTRAG_WIKI_URL', 'https://lightrag-wiki.swarm.hwkdo.com'),
            ],
        ],
        'notebooks' => [
            '1-71394e2d-f51a-4391-b40b-b7f9def13ab1' => 'team-meetings',
        ],
    ],
];
