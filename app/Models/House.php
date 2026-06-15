<?php

namespace App\Models;

use Database\Factories\HouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class House extends Model
{
    /** @use HasFactory<HouseFactory> */
    use HasFactory, SoftDeletes;

    public const ROLE_OWNER = 'owner';

    public const ROLE_RESIDENT = 'resident';

    protected $fillable = [
        'public_id',
        'name',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (House $house): void {
            $house->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['id', 'role', 'joined_at'])
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', self::ROLE_OWNER);
    }

    public function residents(): BelongsToMany
    {
        return $this->members()->wherePivot('role', self::ROLE_RESIDENT);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseInvitation::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(HouseJoinRequest::class);
    }

    public function roleFor(User $user): ?string
    {
        $member = $this->members->firstWhere('id', $user->id);

        if ($member !== null) {
            return $member->pivot->role;
        }

        $member = $this->members()->whereKey($user->id)->first();

        return $member?->pivot->role;
    }

    public function isOwner(User $user): bool
    {
        return $this->members()
            ->whereKey($user->id)
            ->wherePivot('role', self::ROLE_OWNER)
            ->exists();
    }

    public function isMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function ownerCount(): int
    {
        return $this->members()->wherePivot('role', self::ROLE_OWNER)->count();
    }
}
