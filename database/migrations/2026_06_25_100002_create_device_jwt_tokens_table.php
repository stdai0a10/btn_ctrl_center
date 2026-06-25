<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_jwt_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('jti')->unique();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('token_version');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'type']);
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_jwt_tokens');
    }
};
