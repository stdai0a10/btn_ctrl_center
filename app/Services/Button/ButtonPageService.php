<?php

namespace App\Services\Button;

use App\Exceptions\ApiException;
use App\Models\ButtonPage;
use App\Models\ButtonPageItem;
use App\Models\Device;
use App\Models\ProductFunction;
use App\Models\User;
use App\Support\DeviceSerial;
use Illuminate\Support\Facades\DB;

class ButtonPageService
{
    public function create(User $user, string $name, int $layoutColumns): ButtonPage
    {
        $this->ensurePageLimit($user);

        return ButtonPage::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'layout_columns' => $layoutColumns,
            'sort_order' => (int) ButtonPage::query()->where('user_id', $user->id)->max('sort_order') + 1,
        ]);
    }

    public function update(ButtonPage $page, string $name, int $layoutColumns): ButtonPage
    {
        $page->forceFill([
            'name' => $name,
            'layout_columns' => $layoutColumns,
        ])->save();

        return $page->refresh();
    }

    public function delete(ButtonPage $page): void
    {
        $page->delete();
    }

    /**
     * @param  list<string>  $publicIds
     */
    public function reorder(User $user, array $publicIds): void
    {
        $pages = ButtonPage::query()
            ->where('user_id', $user->id)
            ->whereIn('public_id', $publicIds)
            ->get()
            ->keyBy('public_id');

        if ($pages->count() !== count($publicIds)) {
            throw new ApiException('Button page not found.', 'BUTTON_PAGE_NOT_FOUND', 404);
        }

        DB::transaction(function () use ($pages, $publicIds): void {
            foreach (array_values($publicIds) as $index => $publicId) {
                $pages[$publicId]->forceFill(['sort_order' => $index])->save();
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $buttons
     */
    public function saveLayout(User $user, ButtonPage $page, string $name, int $layoutColumns, array $buttons): ButtonPage
    {
        if (count($buttons) > 100) {
            throw new ApiException('Button limit exceeded.', 'BUTTON_PAGE_BUTTON_LIMIT_EXCEEDED');
        }

        $positions = array_map(fn (array $button): int => (int) $button['position'], $buttons);
        if (count($positions) !== count(array_unique($positions))) {
            throw new ApiException('Button position duplicated.', 'BUTTON_POSITION_DUPLICATED');
        }

        return DB::transaction(function () use ($user, $page, $name, $layoutColumns, $buttons): ButtonPage {
            $existing = $page->items()->get()->keyBy('public_id');
            $keptIds = [];

            $page->forceFill([
                'name' => $name,
                'layout_columns' => $layoutColumns,
            ])->save();

            foreach ($buttons as $button) {
                $publicId = $button['public_id'] ?? null;
                $existingItem = is_string($publicId) ? $existing->get($publicId) : null;
                $device = Device::query()
                    ->where('serial_number', DeviceSerial::normalize((string) $button['device_serial_number']))
                    ->firstOrFail();
                $function = ProductFunction::query()
                    ->where('code', (string) $button['product_function_code'])
                    ->firstOrFail();

                $targetChanged = $existingItem === null
                    || (int) $existingItem->device_id !== (int) $device->id
                    || (int) $existingItem->product_function_id !== (int) $function->id;

                if ($targetChanged && ! $this->targetIsSelectable($user, $device, $function)) {
                    throw new ApiException('Button target is not selectable.', 'BUTTON_TARGET_NOT_SELECTABLE');
                }

                $attributes = [
                    'device_id' => $device->id,
                    'product_function_id' => $function->id,
                    'position' => (int) $button['position'],
                    'shape' => (string) $button['shape'],
                    'background_color' => (string) $button['background_color'],
                    'content_type' => (string) $button['content_type'],
                    'icon_key' => $button['icon_key'] ?? null,
                    'label' => $button['label'] ?? null,
                    'foreground_color' => (string) $button['foreground_color'],
                ];

                if ($existingItem !== null) {
                    $existingItem->forceFill($attributes)->save();
                    $keptIds[] = $existingItem->id;
                    continue;
                }

                $created = $page->items()->create($attributes);
                $keptIds[] = $created->id;
            }

            $page->items()->whereNotIn('id', $keptIds)->delete();

            return $page->refresh()->load('items.device.currentRoom', 'items.device.product', 'items.productFunction');
        });
    }

    private function ensurePageLimit(User $user): void
    {
        if (ButtonPage::query()->where('user_id', $user->id)->count() >= 50) {
            throw new ApiException('Button page limit exceeded.', 'BUTTON_PAGE_LIMIT_EXCEEDED');
        }
    }

    private function targetIsSelectable(User $user, Device $device, ProductFunction $function): bool
    {
        $device->loadMissing(['currentRoom', 'product']);

        return $device->currentRoom !== null
            && $device->currentRoom->isMember($user)
            && $device->is_enabled
            && ! $device->is_system_disabled
            && $device->product_id !== null
            && (int) $function->product_id === (int) $device->product_id
            && $function->is_enabled
            && ! (bool) $device->product?->is_locked;
    }
}
