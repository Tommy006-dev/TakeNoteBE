<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $fillable = [
        'user_id', 'name'
    ];

    // Label thuộc về 1 User sở hữu
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Label được gắn cho nhiều Notes
    public function notes()
    {
        return $this->belongsToMany(Note::class, 'note_label');
    }
}