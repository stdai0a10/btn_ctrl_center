<?php

namespace Tests\Feature\Manage;

use App\Models\ManageActionLog;
use App\Models\User;
use App\Services\Manage\ManageActionLogger;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ManageActionLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
    }

    public function test_cli_log_separates_actor_from_target_and_preserves_before_after_metadata(): void
    {
        $target = User::factory()->create();

        app(ManageActionLogger::class)->forCli(
            command: 'system-admin:add',
            action: 'system_admin.grant',
            targetType: 'user',
            targetId: $target->id,
            targetPublicId: $target->public_id,
            metadata: [
                'before' => ['roles' => []],
                'after' => ['roles' => ['system_admin']],
            ],
        );

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_type' => 'cli',
            'actor_user_id' => null,
            'action' => 'system_admin.grant',
            'target_id' => $target->id,
        ]);
        $this->assertSame(
            ['roles' => ['system_admin']],
            ManageActionLog::query()->firstOrFail()->metadata['after'],
        );
    }

    public function test_manage_user_log_supports_no_op_metadata(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $request = Request::create('/manage/api/service-managers/'.$target->public_id.'/grant', 'POST');
        $request->setUserResolver(fn () => $actor);

        app(ManageActionLogger::class)->forManageUser(
            request: $request,
            action: 'service_manager.grant',
            targetType: 'user',
            targetId: $target->id,
            targetPublicId: $target->public_id,
            metadata: ['no_op' => true],
        );

        $log = ManageActionLog::query()->firstOrFail();
        $this->assertSame('manage_user', $log->actor_type);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertTrue($log->metadata['no_op']);
    }
}
