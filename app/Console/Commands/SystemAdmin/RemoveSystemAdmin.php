<?php

namespace App\Console\Commands\SystemAdmin;

use App\Models\User;
use App\Services\Manage\ManageActionLogger;
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

        app(ManageActionLogger::class)->forCli(
            command: $this->getName(),
            action: 'system_admin.revoke',
            targetType: 'user',
            targetId: $user->id,
            targetPublicId: $user->public_id,
            metadata: [
                'before' => ['roles' => $before],
                'after' => ['roles' => $after],
            ],
        );

        $this->info("Removed system administrator from {$publicId}.");

        return self::SUCCESS;
    }
}
