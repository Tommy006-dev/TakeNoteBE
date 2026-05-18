<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('activation_token')->nullable(); // Dùng để xác thực email
            
            // Cài đặt User (User Preferences)
            $table->string('font_style')->default('Arial');
            $table->string('default_note_color')->default('#ffffff');
            $table->string('theme')->default('light');
            
            $table->rememberToken();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');

    }
};
