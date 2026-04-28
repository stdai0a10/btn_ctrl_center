<?php

namespace App\Models\Auth;

use App\Models\User;
use Database\Factories\UserEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmail extends Model
{
    /** @use HasFactory<UserEmailFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'is_verified',
        'verified_at',
        'is_primary',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'is_primary' => 'boolean',
            'reserved_until' => 'datetime',
        ];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): Factory
    {
        return UserEmailFactory::new();
    }
}
