<?php

namespace App\Console\Commands\SystemAdmin;

use App\Models\User;
use App\Services\Manage\ManageActionLogger;
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

        app(ManageActionLogger::class)->forCli(
            command: $this->getName(),
            action: 'system_admin.grant',
            targetType: 'user',
            targetId: $user->id,
            targetPublicId: $user->public_id,
            metadata: [
                'before' => ['roles' => $before],
                'after' => ['roles' => $after],
            ],
        );
        $this->info("Granted system administrator to {$publicId}.");

        return self::SUCCESS;
    }
}
