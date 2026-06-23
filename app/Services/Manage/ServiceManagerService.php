<?php

namespace App\Services\Manage;

use App\Models\User;
use App\Support\ManagementRbac;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceManagerService
{
    public function __construct(private readonly ManageActionLogger $logger) {}

    /**
     * @return array{changed: bool, has_service_manager: bool}
     */
    public function grant(Request $request, User $target): array
    {
        if ($target->status !== 'active') {
            throw ValidationException::withMessages([
                'user' => '只能授權啟用中的使用者。',
            ]);
        }

        return DB::transaction(function () use ($request, $target): array {
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $before = $target->getRoleNames()->sort()->values()->all();
            $changed = ! $target->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE);

            if ($changed) {
                $target->assignRole(ManagementRbac::SERVICE_MANAGER_ROLE);
            }

            $after = $target->refresh()->getRoleNames()->sort()->values()->all();
            $this->logger->forManageUser(
                request: $request,
                action: 'service_manager.grant',
                targetType: 'user',
                targetId: $target->id,
                targetPublicId: $target->public_id,
                metadata: [
                    'no_op' => ! $changed,
                    'before' => ['roles' => $before],
                    'after' => ['roles' => $after],
                ],
            );

            return [
                'changed' => $changed,
                'has_service_manager' => true,
            ];
        });
    }

    /**
     * @return array{changed: bool, has_service_manager: bool}
     */
    public function revoke(Request $request, User $target): array
    {
        return DB::transaction(function () use ($request, $target): array {
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $before = $target->getRoleNames()->sort()->values()->all();
            $changed = $target->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE);

            if ($changed) {
                $target->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
            }

            $after = $target->refresh()->getRoleNames()->sort()->values()->all();
            $this->logger->forManageUser(
                request: $request,
                action: 'service_manager.revoke',
                targetType: 'user',
                targetId: $target->id,
                targetPublicId: $target->public_id,
                metadata: [
                    'no_op' => ! $changed,
                    'before' => ['roles' => $before],
                    'after' => ['roles' => $after],
                ],
            );

            return [
                'changed' => $changed,
                'has_service_manager' => false,
            ];
        });
    }
}
