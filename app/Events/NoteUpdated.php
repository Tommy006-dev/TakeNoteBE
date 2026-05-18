<?php

namespace App\Events;

use App\Models\Note;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; 
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $note;

    public function __construct(Note $note)
    {
        $this->note = $note;
    }

    // Phát tín hiệu vào kênh Phòng họp của riêng ghi chú này
    public function broadcastOn()
    {
        return new PresenceChannel('note.' . $this->note->id);
    }

    // Dữ liệu sẽ bay sang React
    public function broadcastWith()
    {
        return [
            'id' => $this->note->id,
            'title' => $this->note->title,
            'content' => $this->note->content,
            'color' => $this->note->color
        ];
    }
}