<?php

namespace Tests\Integration;

use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\Room;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql')]
class MySqlDeviceConcurrencyTest extends TestCase
{
    public function test_concurrent_device_transfers_are_serialized_by_row_lock(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL integration test database.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl_fork to issue simultaneous transfers.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        [$firstOwner, $secondOwner] = User::factory()->count(2)->create();
        $firstRoom = $this->createRoom($firstOwner, 'Concurrent A');
        $secondRoom = $this->createRoom($secondOwner, 'Concurrent B');
        $device = Device::factory()->create([
            'serial_number' => 'DEVICE-CONCURRENT',
            'secret_hash' => Hash::make('concurrent-secret'),
        ]);

        $children = [
            [$firstOwner->id, $firstRoom->id],
            [$secondOwner->id, $secondRoom->id],
        ];
        $pids = [];

        foreach ($children as [$ownerId, $roomId]) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                DB::purge();
                DB::reconnect();

                app(DeviceService::class)->attachToRoom(
                    Room::query()->findOrFail($roomId),
                    User::query()->findOrFail($ownerId),
                    'DEVICE-CONCURRENT',
                    'concurrent-secret',
                    null,
                    false,
                );

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        DB::purge();
        DB::reconnect();

        $device->refresh();
        $this->assertContains($device->current_room_id, [$firstRoom->id, $secondRoom->id]);

        $logs = DeviceTransferLog::query()
            ->where('device_id', $device->id)
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertNull($logs[0]->from_room_id);
        $this->assertSame($logs[0]->to_room_id, $logs[1]->from_room_id);
        $this->assertSame($device->current_room_id, $logs[1]->to_room_id);
    }

    private function createRoom(User $owner, string $name): Room
    {
        $room = Room::factory()->create([
            'name' => $name,
            'created_by_user_id' => $owner->id,
        ]);
        $room->members()->attach($owner->id, [
            'role' => Room::ROLE_OWNER,
            'joined_at' => now(),
        ]);

        return $room;
    }
}
