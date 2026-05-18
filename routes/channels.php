<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Note;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Kiểm tra xem user có phải chủ note hoặc được share không thì mới cho vào phòng
Broadcast::channel('note.{id}', function ($user, $id) {
    $note = Note::find($id);
    if ($note && ($note->user_id === $user->id || $note->sharedUsers->contains($user->id))) {
        // Trả về info để React đếm số người đang online (Presence Channel)
        return ['id' => $user->id, 'name' => $user->name]; 
    }
    return true;
});
