<?php

namespace App\Console\Commands;

use App\Models\Auth\EmailVerificationRequest;
use App\Models\Auth\PasswordResetRequest;
use App\Models\Auth\SecurityReauthLog;
use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Console\Command;

class CleanupExpiredAuthArtifacts extends Command
{
    protected $signature = 'auth:cleanup-expired';

    protected $description = 'Expire auth request tokens and remove stale unverified account data.';

    public function handle(): int
    {
        $now = now();

        $expiredEmailRequests = EmailVerificationRequest::query()
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '<=', $now)
            ->update(['invalidated_at' => $now]);

        $expiredPasswordRequests = PasswordResetRequest::query()
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '<=', $now)
            ->update(['invalidated_at' => $now]);

        $pendingUsers = User::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $now->copy()->subDay())
            ->whereDoesntHave('emails', fn ($query) => $query->where('is_verified', true))
            ->whereDoesntHave('authProviders')
            ->delete();

        $expiredReservations = UserEmail::query()
            ->where('is_verified', false)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<=', $now)
            ->delete();

        $expiredReauthLogs = SecurityReauthLog::query()
            ->where('expires_at', '<=', $now->copy()->subDay())
            ->delete();

        $this->components->info(sprintf(
            'Cleaned auth artifacts: email_requests=%d password_requests=%d pending_users=%d email_reservations=%d reauth_logs=%d',
            $expiredEmailRequests,
            $expiredPasswordRequests,
            $pendingUsers,
            $expiredReservations,
            $expiredReauthLogs,
        ));

        return self::SUCCESS;
    }
}
