<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shared_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->onDelete('cascade'); // Nốt được chia sẻ
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Người được nhận
            $table->string('permission')->default('read-only'); // Quyền: read-only hoặc edit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_notes');
    }
};