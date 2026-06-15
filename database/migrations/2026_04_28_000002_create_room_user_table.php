<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index('user_id');
            $table->index(['room_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_user');
    }
};
