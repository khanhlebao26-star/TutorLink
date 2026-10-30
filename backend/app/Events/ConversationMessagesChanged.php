<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ConversationMessagesChanged implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public string $queue = 'broadcasts';

    public function __construct(
        public readonly int $conversationId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'conversations.'.$this->conversationId,
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.messages_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
        ];
    }
}
