<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_transfer_logs', function (Blueprint $table): void {
            $table->dropForeign(['transferred_by_user_id']);
        });

        Schema::table('device_transfer_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('transferred_by_user_id')->nullable()->change();
            $table->foreign('transferred_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('device_transfer_logs')->whereNull('transferred_by_user_id')->delete();

        Schema::table('device_transfer_logs', function (Blueprint $table): void {
            $table->dropForeign(['transferred_by_user_id']);
        });

        Schema::table('device_transfer_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('transferred_by_user_id')->nullable(false)->change();
            $table->foreign('transferred_by_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
