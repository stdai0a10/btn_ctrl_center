<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manage_action_logs', function (Blueprint $table): void {
            $table->string('actor_type', 40)->default('manage_user')->index()->after('id');
            $table->foreignId('actor_user_id')
                ->nullable()
                ->after('actor_type')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('manage_action_logs')->update([
            'actor_user_id' => DB::raw('user_id'),
        ]);

        DB::table('manage_action_logs')
            ->where(function ($query): void {
                $query->where('metadata', 'like', '%"actor_type":"cli"%')
                    ->orWhere('metadata', 'like', '%"actor_type": "cli"%');
            })
            ->update([
                'actor_type' => 'cli',
                'actor_user_id' => null,
            ]);

        Schema::table('manage_action_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('manage_action_logs', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('manage_action_logs')
            ->whereNotNull('actor_user_id')
            ->update([
                'user_id' => DB::raw('actor_user_id'),
            ]);

        Schema::table('manage_action_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('actor_user_id');
            $table->dropColumn('actor_type');
        });
    }
};
