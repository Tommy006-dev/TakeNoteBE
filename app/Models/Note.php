<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'user_id', 'title', 'content', 'images', 'color', 'is_pinned', 'password'
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'images' => 'array', 
    ];

    // Note thuộc về 1 User sở hữu
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Note có nhiều Labels
    public function labels()
    {
        return $this->belongsToMany(Label::class, 'note_label');
    }

    // Ghi chú thuộc về người tạo (Chủ sở hữu)
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Danh sách những người được chia sẻ ghi chú này
    public function sharedUsers()
    {
        return $this->belongsToMany(User::class, 'note_user')
                    ->withPivot('permission')
                    ->withTimestamps();
    }
}