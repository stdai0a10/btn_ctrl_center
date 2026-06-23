<?php

namespace App\Services\Manage;

use App\Models\ManageActionLog;
use App\Models\User;
use Illuminate\Http\Request;

class ManageActionLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function forManageUser(
        Request $request,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetPublicId = null,
        array $metadata = [],
    ): ManageActionLog {
        return $this->create(
            actorType: 'manage_user',
            actorUser: $request->user(),
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            targetPublicId: $targetPublicId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function forCli(
        string $command,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetPublicId = null,
        array $metadata = [],
    ): ManageActionLog {
        return $this->create(
            actorType: 'cli',
            actorUser: null,
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            targetPublicId: $targetPublicId,
            metadata: [
                'command' => $command,
                'environment' => app()->environment(),
                'sapi' => PHP_SAPI,
                ...$metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function create(
        string $actorType,
        ?User $actorUser,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetPublicId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $metadata = [],
    ): ManageActionLog {
        return ManageActionLog::query()->create([
            'actor_type' => $actorType,
            'actor_user_id' => $actorUser?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_public_id' => $targetPublicId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 2000),
            'metadata' => $metadata,
        ]);
    }
}
