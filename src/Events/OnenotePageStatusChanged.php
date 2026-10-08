<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnenotePageStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $pageId,
        public string $status,
        public ?string $error,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('msgraph-onenote-rag'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'onenote.page.status';
    }

    /**
     * @return array{pageId: string, status: string, error: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'pageId' => $this->pageId,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
