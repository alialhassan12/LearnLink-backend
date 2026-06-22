<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        // Assign $this->message FIRST, then eager-load relationships
        $this->message = $message->load(['sender', 'conversation.participants']);
    }

    /**
     * Get the channels the event should broadcast on.
     * Broadcasts to every participant's private user channel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to all participants of this conversation
        foreach ($this->message->conversation->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant->user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'last_message'    => [
                'id'         => $this->message->id,
                'content'    => $this->message->content,
                'type'       => $this->message->type,
                'file_url'   => $this->message->file_url,
                'sender_id'  => $this->message->sender_id,
                'created_at' => $this->message->created_at,
            ],
            'updated_at'  => $this->message->created_at,
            'sender_id'   => $this->message->sender_id,
        ];
    }
}
