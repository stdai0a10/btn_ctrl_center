<?php

namespace App\Console\Commands\SystemAdmin;

use App\Models\ManageActionLog;
use App\Models\User;
use App\Support\ManagementRbac;
use Illuminate\Console\Command;

class AddSystemAdmin extends Command
{
    protected $signature = 'system-admin:add {user_public_id}';

    protected $description = 'Grant the system administrator role to an active user';

    public function handle(): int
    {
        $publicId = (string) $this->argument('user_public_id');
        $user = User::query()->where('public_id', $publicId)->first();

        if ($user === null) {
            $this->error("User {$publicId} was not found.");

            return self::FAILURE;
        }

        if ($user->status !== 'active') {
            $this->error("User {$publicId} is not active.");

            return self::FAILURE;
        }

        if ($user->hasRole(ManagementRbac::SYSTEM_ADMIN_ROLE)) {
            $this->warn("User {$publicId} is already a system administrator.");

            return self::SUCCESS;
        }

        $before = $user->getRoleNames()->sort()->values()->all();
        $user->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $after = $user->refresh()->getRoleNames()->sort()->values()->all();

        $this->logAction($user, 'system_admin.grant', $before, $after);
        $this->info("Granted system administrator to {$publicId}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function logAction(User $user, string $action, array $before, array $after): void
    {
        ManageActionLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
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
    }
}
