<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Auth\AuthAttemptLog;
use App\Models\ManageActionLog;
use App\Models\ManageLoginLog;
use App\Models\User;
use App\Services\Manage\ServiceManagerService;
use App\Support\ManagementRbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ServiceManagerController extends ApiController
{
    public function __construct(private readonly ServiceManagerService $service) {}

    #[OA\Get(
        path: '/manage/api/service-managers',
        operationId: 'manageServiceManagersIndex',
        summary: 'List service managers and candidates',
        security: [['sessionCookie' => []]],
        tags: ['Manage Service Managers'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/QuerySearch'),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string', enum: ['service_manager', 'not_service_manager', 'all'])),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 20)),
            new OA\Parameter(ref: '#/components/parameters/QueryPage'),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:service_manager,not_service_manager,all'],
            'status' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $role = $validated['role'] ?? ($search === '' ? 'service_manager' : 'all');

        $users = User::query()
            ->with(['primaryEmail', 'roles'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('public_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('primaryEmail', fn (Builder $query) => $query->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($role === 'service_manager', fn (Builder $query) => $query->role(ManagementRbac::SERVICE_MANAGER_ROLE))
            ->when($role === 'not_service_manager', fn (Builder $query) => $query->whereDoesntHave(
                'roles',
                fn (Builder $query) => $query->where('name', ManagementRbac::SERVICE_MANAGER_ROLE),
            ))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $users->getCollection()->transform(fn (User $user): array => $this->userPayload($user));

        return $this->response([
            'items' => $users->items(),
            'pagination' => $this->paginationPayload($users),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    #[OA\Get(
        path: '/manage/api/service-managers/{user_public_id}',
        operationId: 'manageServiceManagersShow',
        summary: 'Get service manager details and recent activity',
        security: [['sessionCookie' => []]],
        tags: ['Manage Service Managers'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathUserPublicId')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(string $userPublicId)
    {
        $user = $this->findUser($userPublicId);

        $loginLogs = ManageLoginLog::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ManageLoginLog $log): array => [
                'id' => $log->id,
                'success' => $log->success,
                'failure_reason' => $log->failure_reason,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ]);

        $recentActions = ManageActionLog::query()
            ->with('actor')
            ->where(function (Builder $query) use ($user): void {
                $query->where('actor_user_id', $user->id)
                    ->orWhere(fn (Builder $query) => $query
                        ->where('target_type', 'user')
                        ->where('target_id', $user->id));
            })
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (ManageActionLog $log): array => $this->actionPayload($log));

        return $this->response([
            ...$this->userPayload($user),
            'roles' => $user->getRoleNames()->sort()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
            'recent_manage_logins' => $loginLogs,
            'recent_manage_actions' => $recentActions,
            'recent_role_changes' => $recentActions
                ->whereIn('action', ['service_manager.grant', 'service_manager.revoke'])
                ->values(),
        ]);
    }

    #[OA\Post(
        path: '/manage/api/service-managers/{user_public_id}/grant',
        operationId: 'manageServiceManagersGrant',
        summary: 'Grant the service manager role',
        security: [['sessionCookie' => []]],
        tags: ['Manage Service Managers'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathUserPublicId')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function grant(Request $request, string $userPublicId)
    {
        $result = $this->service->grant($request, $this->findUser($userPublicId));

        return $this->response($result, $result['changed'] ? '已授予服務管理員身分。' : '使用者已是服務管理員。');
    }

    #[OA\Post(
        path: '/manage/api/service-managers/grant-many',
        operationId: 'manageServiceManagersGrantMany',
        summary: 'Grant the service manager role to multiple users',
        security: [['sessionCookie' => []]],
        tags: ['Manage Service Managers'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ServiceManagerBatchRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function grantMany(Request $request)
    {
        $publicIds = $this->validatedPublicIds($request);
        $results = [];

        foreach ($publicIds as $publicId) {
            $results[$publicId] = $this->service->grant($request, $this->findUser($publicId));
        }

        return $this->response(['results' => $results], '批次授權完成。');
    }

    #[OA\Post(
        path: '/manage/api/service-managers/{user_public_id}/revoke',
        operationId: 'manageServiceManagersRevoke',
        summary: 'Revoke the service manager role',
        security: [['sessionCookie' => []]],
        tags: ['Manage Service Managers'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathUserPublicId')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function revoke(Request $request, string $userPublicId)
    {
        $result = $this->service->revoke($request, $this->findUser($userPublicId));

        return $this->response($result, $result['changed'] ? '已撤銷服務管理員身分。' : '使用者目前不是服務管理員。');
    }

    #[OA\Post(
        path: '/manage/api/service-managers/revoke-many',
        operationId: 'manageServiceManagersRevokeMany',
        summary: 'Revoke the service manager role from multiple users',
        security: [['sessionCookie' => []]],
        tags: ['Manage Service Managers'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ServiceManagerBatchRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function revokeMany(Request $request)
    {
        $publicIds = $this->validatedPublicIds($request);
        $results = [];

        foreach ($publicIds as $publicId) {
            $results[$publicId] = $this->service->revoke($request, $this->findUser($publicId));
        }

        return $this->response(['results' => $results], '批次撤銷完成。');
    }

    private function findUser(string $userPublicId): User
    {
        return User::query()
            ->with(['primaryEmail', 'roles'])
            ->where('public_id', $userPublicId)
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function validatedPublicIds(Request $request): array
    {
        $validated = $request->validate([
            'user_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_public_ids.*' => ['required', 'string', 'distinct', 'exists:users,public_id'],
        ]);

        return array_values($validated['user_public_ids']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['primaryEmail', 'roles']);
        $email = $user->primaryEmail?->email;
        $lastLoginAt = $email === null ? null : AuthAttemptLog::query()
            ->where('type', 'login')
            ->where('account_key', $email)
            ->where('is_success', true)
            ->latest('created_at')
            ->value('created_at');

        return [
            'public_id' => $user->public_id,
            'display_name' => $user->displayName(),
            'email' => $email,
            'status' => $user->status,
            'has_service_manager' => $user->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE),
            'created_at' => $user->created_at?->toISOString(),
            'last_login_at' => $lastLoginAt ? (string) $lastLoginAt : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionPayload(ManageActionLog $log): array
    {
        return [
            'id' => $log->id,
            'actor_type' => $log->actor_type,
            'actor_public_id' => $log->actor?->public_id,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_public_id' => $log->target_public_id,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationPayload($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
