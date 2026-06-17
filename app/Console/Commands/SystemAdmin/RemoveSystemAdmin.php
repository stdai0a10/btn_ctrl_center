<?php

namespace App\Console\Commands\SystemAdmin;

use App\Models\ManageActionLog;
use App\Models\User;
use App\Support\ManagementRbac;
use Illuminate\Console\Command;

class RemoveSystemAdmin extends Command
{
    protected $signature = 'system-admin:remove {user_public_id}';

    protected $description = 'Remove the system administrator role from a user';

    public function handle(): int
    {
        $publicId = (string) $this->argument('user_public_id');
        $user = User::query()->where('public_id', $publicId)->first();

        if ($user === null) {
            $this->error("User {$publicId} was not found.");

            return self::FAILURE;
        }

        if (! $user->hasRole(ManagementRbac::SYSTEM_ADMIN_ROLE)) {
            $this->warn("User {$publicId} is not a system administrator.");

            return self::SUCCESS;
        }

        $before = $user->getRoleNames()->sort()->values()->all();
        $user->removeRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $after = $user->refresh()->getRoleNames()->sort()->values()->all();

        ManageActionLog::query()->create([
            'user_id' => $user->id,
            'action' => 'system_admin.revoke',
            'target_type' => 'user',
            'target_id' => $user->id,
            'target_public_id' => $user->public_id,
            'metadata' => [
                'actor_type' => 'cli',
                'command' => $this->getName(),
                'environment' => app()->environment(),
                'sapi' => PHP_SAPI,
                'before' => ['roles' => $before],
                'after' => ['roles' => $after],
            ],
        ]);

        $this->info("Removed system administrator from {$publicId}.");

        return self::SUCCESS;
    }
}
