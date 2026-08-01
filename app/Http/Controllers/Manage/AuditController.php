<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\ManageActionLog;
use App\Models\ManageLoginLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditController extends ApiController
{
    public function loginFailures(Request $request)
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'ip' => ['nullable', 'string', 'max:45'],
            'user_public_id' => ['nullable', 'string', 'max:20'],
            'failure_reason' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'locked_only' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);

        $logs = ManageLoginLog::query()
            ->with('user')
            ->where('success', false)
            ->when($validated['email'] ?? null, fn (Builder $query, string $email) => $query->where('email', 'like', "%{$email}%"))
            ->when($validated['ip'] ?? null, fn (Builder $query, string $ip) => $query->where('ip_address', 'like', "%{$ip}%"))
            ->when($validated['user_public_id'] ?? null, fn (Builder $query, string $publicId) => $query->whereHas(
                'user',
                fn (Builder $query) => $query->where('public_id', 'like', "%{$publicId}%"),
            ))
            ->when($validated['failure_reason'] ?? null, fn (Builder $query, string $reason) => $query->where('failure_reason', $reason))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', now()->parse($to)->endOfDay()))
            ->when($validated['locked_only'] ?? false, fn (Builder $query) => $query->whereNotNull('locked_until'))
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        $logs->getCollection()->transform(fn (ManageLoginLog $log): array => [
            'id' => $log->id,
            'user_public_id' => $log->user?->public_id,
            'email' => $log->email,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'failure_reason' => $log->failure_reason,
            'locked_until' => $log->locked_until?->toISOString(),
            'success' => $log->success,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return $this->response([
            ...$this->paginatedPayload($logs),
            'filters' => $validated,
        ]);
    }

    public function actions(Request $request)
    {
        $validated = $request->validate([
            'actor' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'target_type' => ['nullable', 'string', 'max:80'],
            'target_public_id' => ['nullable', 'string', 'max:255'],
            'ip' => ['nullable', 'string', 'max:45'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);

        $logs = ManageActionLog::query()
            ->with('actor')
            ->when($validated['actor'] ?? null, function (Builder $query, string $actor): void {
                $query->where(function (Builder $query) use ($actor): void {
                    $query->where('actor_type', 'like', "%{$actor}%")
                        ->orWhereHas('actor', function (Builder $query) use ($actor): void {
                            $query->where('public_id', 'like', "%{$actor}%")
                                ->orWhere('name', 'like', "%{$actor}%");
                        });
                });
            })
            ->when($validated['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', 'like', "%{$action}%"))
            ->when($validated['target_type'] ?? null, fn (Builder $query, string $targetType) => $query->where('target_type', $targetType))
            ->when($validated['target_public_id'] ?? null, fn (Builder $query, string $publicId) => $query->where('target_public_id', 'like', "%{$publicId}%"))
            ->when($validated['ip'] ?? null, fn (Builder $query, string $ip) => $query->where('ip_address', 'like', "%{$ip}%"))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', now()->parse($to)->endOfDay()))
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        $logs->getCollection()->transform(fn (ManageActionLog $log): array => [
            'id' => $log->id,
            'actor_type' => $log->actor_type,
            'actor_public_id' => $log->actor?->public_id,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_public_id' => $log->target_public_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return $this->response([
            ...$this->paginatedPayload($logs),
            'filters' => $validated,
        ]);
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
