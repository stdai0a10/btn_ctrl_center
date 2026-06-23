<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->boolean('is_enabled')->default(true)->after('is_locked');
        });

        Schema::table('device_transfer_logs', function (Blueprint $table): void {
            $table->string('from_room_public_id_snapshot', 26)->nullable()->after('transferred_by_user_id');
            $table->string('to_room_public_id_snapshot', 26)->nullable()->after('from_room_public_id_snapshot');
            $table->string('transferred_by_user_public_id_snapshot', 20)->nullable()->after('to_room_public_id_snapshot');
        });

        DB::table('device_transfer_logs')
            ->orderBy('id')
            ->get()
            ->each(function (object $log): void {
                DB::table('device_transfer_logs')
                    ->where('id', $log->id)
                    ->update([
                        'from_room_public_id_snapshot' => $log->from_room_id === null
                            ? null
                            : DB::table('rooms')->where('id', $log->from_room_id)->value('public_id'),
                        'to_room_public_id_snapshot' => $log->to_room_id === null
                            ? null
                            : DB::table('rooms')->where('id', $log->to_room_id)->value('public_id'),
                        'transferred_by_user_public_id_snapshot' => DB::table('users')
                            ->where('id', $log->transferred_by_user_id)
                            ->value('public_id'),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('device_transfer_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'from_room_public_id_snapshot',
                'to_room_public_id_snapshot',
                'transferred_by_user_public_id_snapshot',
            ]);
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('is_enabled');
        });
    }
};
