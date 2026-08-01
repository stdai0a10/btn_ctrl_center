<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('secret_hash');
            $table->foreignId('current_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('name')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->index('current_room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
