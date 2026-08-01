<?php

namespace App\Console\Commands\SystemAdmin;

use App\Models\ManageActionLog;
use App\Models\ManageLoginLog;
use App\Models\Permission\Role;
use App\Support\ManagementRbac;
use Illuminate\Console\Command;

class ListSystemAdmins extends Command
{
    protected $signature = 'system-admin:list';

    protected $description = 'List all system administrators';

    public function handle(): int
    {
        $role = Role::query()
            ->where('name', ManagementRbac::SYSTEM_ADMIN_ROLE)
            ->where('guard_name', 'web')
            ->first();

        if ($role === null) {
            $this->warn('The system_admin role does not exist.');

            return self::SUCCESS;
        }

        $users = $role->users()
            ->with('primaryEmail')
            ->orderBy('users.public_id')
            ->get();

        $rows = $users->map(function ($user): array {
            $grantedAt = ManageActionLog::query()
                ->where('action', 'system_admin.grant')
                ->where('target_id', $user->id)
                ->latest('created_at')
                ->value('created_at');
            $lastLoginAt = ManageLoginLog::query()
                ->where('user_id', $user->id)
                ->where('success', true)
                ->latest('created_at')
                ->value('created_at');

            return [
                $user->public_id,
                $user->displayName(),
                $user->primaryEmail?->email ?? '-',
                $user->status,
                $grantedAt ? (string) $grantedAt : '-',
                $lastLoginAt ? (string) $lastLoginAt : '-',
            ];
        })->all();

        $this->table([
            'Public ID',
            'Name',
            'Email',
            'Status',
            'System Admin Since',
            'Last Manage Login',
        ], $rows);

        return self::SUCCESS;
    }
}
