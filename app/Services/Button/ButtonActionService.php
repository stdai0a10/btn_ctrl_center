<?php

namespace App\Services\Button;

use App\Exceptions\ApiException;
use App\Models\ButtonActionJob;
use App\Models\ButtonPageItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ButtonActionService
{
    public function __construct(private readonly ButtonAvailabilityService $availability)
    {
    }

    public function trigger(User $user, ButtonPageItem $button, string $requestId): ButtonActionJob
    {
        $button->loadMissing(['page', 'device.product', 'device.currentRoom', 'productFunction']);

        if ((int) $button->page->user_id !== (int) $user->id) {
            throw new ApiException('Button action is forbidden.', 'BUTTON_ACTION_FORBIDDEN', 403);
        }

        return DB::transaction(function () use ($user, $button, $requestId): ButtonActionJob {
            $existing = ButtonActionJob::query()
                ->where('user_id', $user->id)
                ->where('request_id', $requestId)
                ->first();

            if ($existing !== null) {
                if ((int) $existing->button_page_item_id !== (int) $button->id) {
                    throw new ApiException('Request id conflicts with another action.', 'BUTTON_REQUEST_ID_CONFLICT', 409);
                }

                return $existing;
            }

            $active = ButtonActionJob::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [ButtonActionJob::STATUS_QUEUED, ButtonActionJob::STATUS_RUNNING])
                ->first();

            if ($active !== null) {
                throw new ApiException('Button action is already running.', 'BUTTON_ACTION_ALREADY_RUNNING', 409);
            }

            $status = $this->availability->for($user, $button->device, $button->productFunction);
            if (! $status['available']) {
                throw new ApiException($status['message'] ?? 'Button target unavailable.', 'BUTTON_TARGET_UNAVAILABLE');
            }

            $job = ButtonActionJob::query()->create([
                'user_id' => $user->id,
                'request_id' => $requestId,
                'button_page_item_id' => $button->id,
                'device_id' => $button->device_id,
                'product_function_id' => $button->product_function_id,
                'status' => ButtonActionJob::STATUS_QUEUED,
                'source' => ButtonActionJob::SOURCE_BUTTON,
                'payload' => [
                    'type' => 'button_function',
                    'device_serial_number' => $button->device->serial_number,
                    'product_function_code' => $button->productFunction->code,
                ],
                'front_end_timeout_at' => now()->addMinute(),
            ]);

            $job->events()->create([
                'type' => 'created',
                'metadata' => ['button_public_id' => $button->public_id],
            ]);

            return $job->refresh();
        });
    }
}
