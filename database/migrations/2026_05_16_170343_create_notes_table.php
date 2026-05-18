<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Chủ sở hữu
            $table->string('title')->nullable();
            $table->longText('content')->nullable(); // Lưu HTML của ghi chú
            $table->string('color')->default('#ffffff');
            $table->boolean('is_pinned')->default(false);
            $table->string('password')->nullable(); // Mật khẩu bảo mật riêng của ghi chú
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};