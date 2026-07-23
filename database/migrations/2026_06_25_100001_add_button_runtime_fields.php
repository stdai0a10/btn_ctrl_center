<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('long_token_jti')->nullable()->after('is_enabled');
            $table->timestamp('long_token_issued_at')->nullable()->after('long_token_jti');
            $table->timestamp('long_token_expires_at')->nullable()->after('long_token_issued_at');
            $table->timestamp('long_token_revoked_at')->nullable()->after('long_token_expires_at');
            $table->unsignedInteger('token_version')->default(1)->after('long_token_revoked_at');
            $table->string('current_access_jti')->nullable()->after('token_version');
            $table->timestamp('current_access_expires_at')->nullable()->after('current_access_jti');
            $table->string('runner_status')->default('registered')->after('current_access_expires_at');
            $table->unsignedBigInteger('runner_current_job_id')->nullable()->after('runner_status');
            $table->timestamp('runner_last_seen_at')->nullable()->after('runner_current_job_id');
            $table->timestamp('runner_registered_at')->nullable()->after('runner_last_seen_at');
            $table->timestamp('runner_disabled_at')->nullable()->after('runner_registered_at');
            $table->boolean('is_system_disabled')->default(false)->after('runner_disabled_at');
            $table->timestamp('system_disabled_at')->nullable()->after('is_system_disabled');
            $table->foreignId('system_disabled_by_user_id')->nullable()->after('system_disabled_at')->constrained('users')->nullOnDelete();

            $table->index('runner_status');
            $table->index('runner_last_seen_at');
            $table->index('is_system_disabled');
        });

        Schema::table('product_functions', function (Blueprint $table): void {
            $table->boolean('is_enabled')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_functions', function (Blueprint $table): void {
            $table->dropColumn('is_enabled');
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('system_disabled_by_user_id');
            $table->dropIndex(['runner_status']);
            $table->dropIndex(['runner_last_seen_at']);
            $table->dropIndex(['is_system_disabled']);
            $table->dropColumn([
                'long_token_jti',
                'long_token_issued_at',
                'long_token_expires_at',
                'long_token_revoked_at',
                'token_version',
                'current_access_jti',
                'current_access_expires_at',
                'runner_status',
                'runner_current_job_id',
                'runner_last_seen_at',
                'runner_registered_at',
                'runner_disabled_at',
                'is_system_disabled',
                'system_disabled_at',
            ]);
        });
    }
};
