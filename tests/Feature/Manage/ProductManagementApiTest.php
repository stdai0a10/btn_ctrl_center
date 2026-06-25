<?php

namespace Tests\Feature\Manage;

use App\Models\Device;
use App\Models\ManageActionLog;
use App\Models\Product;
use App\Models\ProductFunction;
use App\Models\Room;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole(ManagementRbac::SERVICE_MANAGER_ROLE);
    }

    public function test_service_manager_can_list_and_view_products(): void
    {
        $product = Product::factory()->create([
            'model_number' => 'BTN-001',
            'name' => 'Smart Button',
        ]);
        ProductFunction::factory()->create([
            'product_id' => $product->id,
            'description' => '短按觸發',
        ]);
        Device::factory()->create(['product_id' => $product->id]);

        $this->asManageUser()
            ->getJson('/manage/api/products?search=btn')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.public_id', $product->public_id)
            ->assertJsonPath('data.items.0.model_number', 'BTN-001')
            ->assertJsonPath('data.items.0.function_count', 1)
            ->assertJsonPath('data.items.0.device_count', 1);

        $this->asManageUser()
            ->getJson("/manage/api/products/{$product->public_id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $product->public_id)
            ->assertJsonPath('data.functions.0.description', '短按觸發')
            ->assertJsonPath('data.devices.pagination.total', 1);

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'products.detail.view',
            'target_type' => 'product',
            'target_public_id' => $product->public_id,
        ]);
    }

    public function test_product_suggestions_return_minimal_matching_products(): void
    {
        Product::factory()->create(['model_number' => 'BTN-001', 'name' => 'Smart Button']);
        Product::factory()->create(['model_number' => 'SENSOR-001', 'name' => 'Sensor']);

        $this->asManageUser()
            ->getJson('/manage/api/products?suggest=1&search=btn&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.model_number', 'BTN-001')
            ->assertJsonMissingPath('data.items.0.device_count')
            ->assertJsonMissingPath('data.items.0.function_count');
    }

    public function test_system_admin_can_create_and_update_product_with_normalized_unique_model_number(): void
    {
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $response = $this->asManageUser()
            ->postJson('/manage/api/products', [
                'model_number' => ' btn-001 ',
                'name' => 'Smart Button',
            ])
            ->assertCreated()
            ->assertJsonPath('data.model_number', 'BTN-001')
            ->assertJsonPath('data.name', 'Smart Button');

        $product = Product::query()->where('model_number', 'BTN-001')->firstOrFail();
        $this->assertSame($product->public_id, $response->json('data.public_id'));

        $this->asManageUser()
            ->patchJson("/manage/api/products/{$product->public_id}", [
                'model_number' => ' btn-002 ',
                'name' => 'Updated Button',
            ])
            ->assertOk()
            ->assertJsonPath('data.model_number', 'BTN-002')
            ->assertJsonPath('data.name', 'Updated Button');

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'products.create',
            'target_type' => 'product',
            'target_public_id' => $product->public_id,
        ]);

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'products.update',
            'target_type' => 'product',
            'target_public_id' => $product->public_id,
        ]);
    }

    public function test_system_admin_can_lock_product_and_locked_product_cannot_be_changed(): void
    {
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $product = Product::factory()->create([
            'model_number' => 'BTN-LOCK',
            'name' => 'Lockable Product',
        ]);
        $function = ProductFunction::factory()->create([
            'product_id' => $product->id,
            'description' => 'Before lock',
        ]);

        $this->asManageUser()
            ->postJson("/manage/api/products/{$product->public_id}/lock")
            ->assertOk()
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('message', '產品已鎖定。');

        $this->assertTrue($product->refresh()->is_locked);

        $this->asManageUser()
            ->patchJson("/manage/api/products/{$product->public_id}", [
                'model_number' => 'BTN-LOCK-UPDATED',
                'name' => 'Updated',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRODUCT_LOCKED');

        $this->asManageUser()
            ->postJson("/manage/api/products/{$product->public_id}/functions", [
                'description' => 'After lock',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRODUCT_LOCKED');

        $this->asManageUser()
            ->patchJson("/manage/api/product-functions/{$function->code}", [
                'description' => 'Updated after lock',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRODUCT_LOCKED');

        $this->asManageUser()
            ->deleteJson("/manage/api/product-functions/{$function->code}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRODUCT_LOCKED');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'model_number' => 'BTN-LOCK',
            'name' => 'Lockable Product',
            'is_locked' => true,
        ]);
        $this->assertDatabaseHas('product_functions', [
            'id' => $function->id,
            'description' => 'Before lock',
        ]);
        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'products.lock',
            'target_type' => 'product',
            'target_public_id' => $product->public_id,
        ]);
    }

    public function test_duplicate_product_model_number_is_rejected(): void
    {
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        Product::factory()->create(['model_number' => 'BTN-001']);

        $this->asManageUser()
            ->postJson('/manage/api/products', [
                'model_number' => ' btn-001 ',
                'name' => 'Duplicate',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRODUCT_MODEL_ALREADY_EXISTS');

        $this->assertSame(1, Product::query()->where('model_number', 'BTN-001')->count());
    }

    public function test_system_admin_can_create_update_and_delete_product_functions(): void
    {
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $product = Product::factory()->create();

        $response = $this->asManageUser()
            ->postJson("/manage/api/products/{$product->public_id}/functions", [
                'description' => '短按觸發',
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', '短按觸發');

        $code = $response->json('data.code');
        $this->assertStringStartsWith('PFN-', $code);

        $this->asManageUser()
            ->patchJson("/manage/api/product-functions/{$code}", [
                'description' => '長按觸發',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.description', '長按觸發');

        $this->asManageUser()
            ->deleteJson("/manage/api/product-functions/{$code}")
            ->assertOk()
            ->assertJsonPath('message', '產品功能已刪除。');

        $this->assertDatabaseHas('manage_action_logs', ['action' => 'product_functions.create']);
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'product_functions.update']);
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'product_functions.delete']);
        $this->assertDatabaseMissing('product_functions', ['code' => $code]);
    }

    public function test_service_manager_cannot_mutate_products_or_functions(): void
    {
        $product = Product::factory()->create();
        $function = ProductFunction::factory()->create(['product_id' => $product->id]);

        $this->asManageUser()
            ->postJson('/manage/api/products', [
                'model_number' => 'BTN-FORBIDDEN',
                'name' => 'Forbidden',
            ])
            ->assertForbidden();

        $this->asManageUser()
            ->patchJson("/manage/api/products/{$product->public_id}", [
                'model_number' => 'BTN-FORBIDDEN',
                'name' => 'Forbidden',
            ])
            ->assertForbidden();

        $this->asManageUser()
            ->postJson("/manage/api/products/{$product->public_id}/lock")
            ->assertForbidden();

        $this->asManageUser()
            ->postJson("/manage/api/products/{$product->public_id}/functions", [
                'description' => 'Forbidden',
            ])
            ->assertForbidden();

        $this->asManageUser()
            ->patchJson("/manage/api/product-functions/{$function->code}", [
                'description' => 'Forbidden',
            ])
            ->assertForbidden();

        $this->asManageUser()
            ->deleteJson("/manage/api/product-functions/{$function->code}")
            ->assertForbidden();

        $this->assertDatabaseHas('product_functions', ['code' => $function->code]);
    }

    public function test_product_details_include_device_room_summary(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->create(['created_by_user_id' => $owner->id]);
        $product = Product::factory()->create();
        Device::factory()->create([
            'product_id' => $product->id,
            'serial_number' => 'DEVICE-PRODUCT',
            'current_room_id' => $room->id,
        ]);

        $this->asManageUser()
            ->getJson("/manage/api/products/{$product->public_id}")
            ->assertOk()
            ->assertJsonPath('data.devices.items.0.serial_number', 'DEVICE-PRODUCT')
            ->assertJsonPath('data.devices.items.0.room.public_id', $room->public_id);
    }

    public function test_regular_user_cannot_access_product_management_api(): void
    {
        $regular = User::factory()->create();

        $this->actingAs($regular)
            ->withSession($this->manageSession($regular))
            ->getJson('/manage/api/products')
            ->assertForbidden();
    }

    private function asManageUser(): static
    {
        return $this->actingAs($this->manager)->withSession($this->manageSession($this->manager));
    }

    private function manageSession(User $user): array
    {
        return [
            'manage_authenticated_at' => now()->toISOString(),
            'manage_authenticated_user_id' => $user->id,
        ];
    }
}
