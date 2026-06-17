<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\ManageActionLog;
use App\Models\ManageLoginLog;
use Illuminate\Http\Request;

class AuditController extends ApiController
{
    public function loginFailures(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = ManageLoginLog::query()
            ->with('user')
            ->where('success', false)
            ->latest('created_at')
            ->paginate(20, ['*'], 'page', $validated['page'] ?? 1);

        $logs->getCollection()->transform(fn (ManageLoginLog $log): array => [
            'id' => $log->id,
            'user_public_id' => $log->user?->public_id,
            'email' => $log->email,
            'ip_address' => $log->ip_address,
            'failure_reason' => $log->failure_reason,
            'locked_until' => $log->locked_until?->toISOString(),
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return $this->response($this->paginatedPayload($logs));
    }

    public function actions(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = ManageActionLog::query()
            ->with('actor')
            ->latest('created_at')
            ->paginate(20, ['*'], 'page', $validated['page'] ?? 1);

        $logs->getCollection()->transform(fn (ManageActionLog $log): array => [
            'id' => $log->id,
            'actor_type' => $log->actor_type,
            'user_public_id' => $log->actor?->public_id,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_public_id' => $log->target_public_id,
            'ip_address' => $log->ip_address,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return $this->response($this->paginatedPayload($logs));
    }

    private function paginatedPayload($logs): array
    {
        return [
            'items' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ];
    }
}
