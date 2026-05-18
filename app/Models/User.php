<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'avatar', 'email', 'password', 'activation_token', 
        'font_style', 'default_note_color', 'theme'
    ];

    protected $hidden = [
        'password', 'remember_token', 'activation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // 1 User tạo ra nhiều Notes
    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    // 1 User tạo ra nhiều Labels
    public function labels()
    {
        return $this->hasMany(Label::class);
    }

    // 1 User nhận được nhiều Note chia sẻ từ người khác
    public function receivedNotes()
    {
        return $this->belongsToMany(Note::class, 'shared_notes')
                    ->withPivot('permission')
                    ->withTimestamps();
    }

    // Danh sách các ghi chú mà user này được người khác chia sẻ cho
    public function sharedNotes()
    {
        return $this->belongsToMany(Note::class, 'note_user')
                    ->withPivot('permission')
                    ->withTimestamps();
    }
}