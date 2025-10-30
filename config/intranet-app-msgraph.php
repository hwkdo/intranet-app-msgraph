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
];
