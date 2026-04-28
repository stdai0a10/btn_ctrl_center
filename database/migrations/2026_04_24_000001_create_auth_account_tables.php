<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('primary_email_id')->references('id')->on('user_emails')->nullOnDelete();
        });

        Schema::create('user_auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_user_id');
            $table->string('provider_name_snapshot')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
        });

        Schema::create('email_verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('purpose', 40)->index();
            $table->string('token_hash')->unique();
            $table->dateTime('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('token_hash')->unique();
            $table->dateTime('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('security_reauth_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 40);
            $table->dateTime('passed_at');
            $table->dateTime('expires_at')->index();
            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('auth_attempt_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('account_key')->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->boolean('is_success')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_attempt_logs');
        Schema::dropIfExists('security_reauth_logs');
        Schema::dropIfExists('password_reset_requests');
        Schema::dropIfExists('email_verification_requests');
        Schema::dropIfExists('user_auth_providers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_email_id']);
        });

        Schema::dropIfExists('user_emails');
    }
};
