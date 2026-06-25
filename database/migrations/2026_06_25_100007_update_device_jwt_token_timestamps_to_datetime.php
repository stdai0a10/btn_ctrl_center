<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_jwt_tokens', function (Blueprint $table): void {
            $table->dateTime('issued_at')->change();
            $table->dateTime('expires_at')->change();
            $table->dateTime('revoked_at')->nullable()->change();
            $table->dateTime('last_used_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('device_jwt_tokens', function (Blueprint $table): void {
            $table->dateTime('issued_at')->change();
            $table->dateTime('expires_at')->change();
            $table->timestamp('revoked_at')->nullable()->change();
            $table->timestamp('last_used_at')->nullable()->change();
        });
    }
};
