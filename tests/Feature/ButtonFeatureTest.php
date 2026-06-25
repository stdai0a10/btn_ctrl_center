<?php

namespace Tests\Feature;

use App\Models\ButtonActionJob;
use App\Models\Device;
use App\Models\Product;
use App\Models\ProductFunction;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ButtonFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => Str::random(32)]);
    }

    public function test_user_can_create_button_page_save_layout_and_only_owner_can_access_it(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        [$device, $function] = $this->createSelectableDevice($owner);

        $pagePublicId = $this->actingAs($owner)
            ->postJson('/api/button-pages', [
                'name' => '客廳',
                'layout_columns' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', '客廳')
            ->json('data.public_id');

        $this->actingAs($other)
            ->putJson("/api/button-pages/{$pagePublicId}/layout", [
                'name' => 'bad',
                'layout_columns' => 3,
                'buttons' => [],
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->putJson("/api/button-pages/{$pagePublicId}/layout", [
                'name' => '客廳',
                'layout_columns' => 3,
                'buttons' => [[
                    'device_serial_number' => $device->serial_number,
                    'product_function_code' => $function->code,
                    'position' => 0,
                    'shape' => 'rounded_square',
                    'background_color' => '#2563EB',
                    'content_type' => 'icon',
                    'icon_key' => 'power',
                    'foreground_color' => '#FFFFFF',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.buttons.0.device.serial_number', $device->serial_number)
            ->assertJsonPath('data.buttons.0.function.code', $function->code);
    }

    public function test_selectable_targets_only_include_member_devices_and_product_functions(): void
    {
        $owner = User::factory()->create();
        [$device, $function] = $this->createSelectableDevice($owner);
        Device::factory()->create(['serial_number' => 'DEV-OTHER']);

        $this->actingAs($owner)
            ->getJson('/api/buttons/selectable-targets')
            ->assertOk()
            ->assertJsonPath('data.0.device.serial_number', $device->serial_number)
            ->assertJsonPath('data.0.functions.0.code', $function->code)
            ->assertJsonMissing(['serial_number' => 'DEV-OTHER']);
    }

    public function test_button_action_creates_device_job_and_request_id_is_idempotent(): void
    {
        $owner = User::factory()->create();
        [$device, $function] = $this->createSelectableDevice($owner);
        $button = $this->createButton($owner, $device, $function);

        $first = $this->actingAs($owner)
            ->postJson('/api/button-actions', [
                'button_public_id' => $button,
                'request_id' => 'req-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.job.status', ButtonActionJob::STATUS_QUEUED)
            ->json('data.job.public_id');

        $second = $this->actingAs($owner)
            ->postJson('/api/button-actions', [
                'button_public_id' => $button,
                'request_id' => 'req-1',
            ])
            ->assertCreated()
            ->json('data.job.public_id');

        $this->assertSame($first, $second);
        $this->assertDatabaseHas('button_action_jobs', [
            'public_id' => $first,
            'device_id' => $device->id,
            'product_function_id' => $function->id,
        ]);
    }

    public function test_device_jwt_flow_polls_and_completes_button_job(): void
    {
        $owner = User::factory()->create();
        [$device, $function] = $this->createSelectableDevice($owner, secret: 'runner-secret');
        $button = $this->createButton($owner, $device, $function);

        $jobPublicId = $this->actingAs($owner)
            ->postJson('/api/button-actions', [
                'button_public_id' => $button,
                'request_id' => 'req-runner',
            ])
            ->assertCreated()
            ->json('data.job.public_id');

        $longToken = $this->postJson('/api/device-auth/long-token', [
            'serial_number' => $device->serial_number,
            'secret' => 'runner-secret',
        ])
            ->assertOk()
            ->json('data.long_token');

        $accessToken = $this->withToken($longToken)
            ->postJson("/api/devices/{$device->serial_number}/access-tokens")
            ->assertOk()
            ->json('data.access_token');

        $this->withToken($accessToken)
            ->postJson("/api/devices/{$device->serial_number}/poll", [
                'status' => 'idle',
            ])
            ->assertOk()
            ->assertJsonPath('data.job_id', $jobPublicId)
            ->assertJsonPath('data.payload.product_function_code', $function->code);

        $this->withToken($accessToken)
            ->postJson("/api/device-jobs/{$jobPublicId}/complete", [
                'status' => 'succeeded',
                'result' => ['ok' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.job.status', ButtonActionJob::STATUS_SUCCEEDED);

        $this->assertDatabaseHas('button_action_jobs', [
            'public_id' => $jobPublicId,
            'status' => ButtonActionJob::STATUS_SUCCEEDED,
        ]);
    }

    /**
     * @return array{Device, ProductFunction}
     */
    private function createSelectableDevice(User $owner, string $secret = 'device-secret'): array
    {
        $room = Room::factory()->create([
            'created_by_user_id' => $owner->id,
        ]);
        $room->members()->attach($owner->id, [
            'role' => Room::ROLE_OWNER,
            'joined_at' => now(),
        ]);
        $product = Product::factory()->create(['is_locked' => false]);
        $function = ProductFunction::factory()->create([
            'product_id' => $product->id,
            'is_enabled' => true,
        ]);
        $device = Device::factory()->create([
            'product_id' => $product->id,
            'current_room_id' => $room->id,
            'secret_hash' => Hash::make($secret),
            'runner_status' => 'idle',
            'runner_last_seen_at' => now(),
            'is_enabled' => true,
            'is_system_disabled' => false,
        ]);

        return [$device, $function];
    }

    private function createButton(User $owner, Device $device, ProductFunction $function): string
    {
        $pagePublicId = $this->actingAs($owner)
            ->postJson('/api/button-pages', [
                'name' => '客廳',
                'layout_columns' => 3,
            ])
            ->assertCreated()
            ->json('data.public_id');

        return $this->actingAs($owner)
            ->putJson("/api/button-pages/{$pagePublicId}/layout", [
                'name' => '客廳',
                'layout_columns' => 3,
                'buttons' => [[
                    'device_serial_number' => $device->serial_number,
                    'product_function_code' => $function->code,
                    'position' => 0,
                    'shape' => 'rounded_square',
                    'background_color' => '#2563EB',
                    'content_type' => 'icon',
                    'icon_key' => 'power',
                    'foreground_color' => '#FFFFFF',
                ]],
            ])
            ->assertOk()
            ->json('data.buttons.0.public_id');
    }
}
