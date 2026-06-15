<?php

namespace App\Services;

use App\Models\House;
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
            $house->members()->detach();
            $house->delete();
        });
    }
}
