<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('msgraph-onenote-rag', function ($user): bool {
    return $user->can('manage-app-msgraph');
});
