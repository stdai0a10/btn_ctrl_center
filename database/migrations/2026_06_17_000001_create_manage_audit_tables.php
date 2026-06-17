<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manage_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->boolean('success')->default(false)->index();
            $table->string('failure_reason', 80)->nullable()->index();
            $table->dateTime('locked_until')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('manage_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action')->index();
            $table->string('target_type', 80)->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_public_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manage_action_logs');
        Schema::dropIfExists('manage_login_logs');
    }
};
