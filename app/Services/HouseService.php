<?php

namespace App\Services;

use App\Models\Device;
use App\Models\House;
use App\Models\HouseInvitation;
use App\Models\HouseJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HouseService
{
    public function create(User $creator, string $name): House
    {
        return DB::transaction(function () use ($creator, $name): House {
            $house = House::query()->create([
                'name' => $name,
                'created_by_user_id' => $creator->id,
            ]);

            $house->members()->attach($creator->id, [
                'role' => House::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            return $house->load('members');
        });
    }

    public function update(House $house, string $name): House
    {
        $house->forceFill(['name' => $name])->save();

        return $house->refresh();
    }

    public function delete(House $house): void
    {
        DB::transaction(function () use ($house): void {
            Device::query()
                ->where('current_house_id', $house->id)
                ->update([
                    'current_house_id' => null,
                    'name' => null,
                    'is_locked' => false,
                ]);

            HouseInvitation::query()
                ->where('house_id', $house->id)
                ->where('status', HouseInvitation::STATUS_PENDING)
                ->update([
                    'status' => HouseInvitation::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            HouseJoinRequest::query()
                ->where('house_id', $house->id)
                ->where('status', HouseJoinRequest::STATUS_PENDING)
                ->update([
                    'status' => HouseJoinRequest::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            $house->members()->detach();
            $house->delete();
        });
    }
}
