<?php

namespace App\Models;

use App\Models\Auth\AuthAttemptLog;
use App\Models\Auth\EmailVerificationRequest;
use App\Models\Auth\PasswordResetRequest;
use App\Models\Auth\SecurityReauthLog;
use App\Models\Auth\UserAuthProvider;
use App\Models\Auth\UserEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'password',
        'primary_email_id',
        'last_reauth_at',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_reauth_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->public_id ??= self::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        do {
            $publicId = Str::upper(Str::random(12));
        } while (self::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function displayName(): string
    {
        return $this->name ?: $this->public_id;
    }

    public function emails(): HasMany
    {
        return $this->hasMany(UserEmail::class);
    }

    public function primaryEmail(): BelongsTo
    {
        return $this->belongsTo(UserEmail::class, 'primary_email_id');
    }

    public function authProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class);
    }

    public function emailVerificationRequests(): HasMany
    {
        return $this->hasMany(EmailVerificationRequest::class);
    }

    public function passwordResetRequests(): HasMany
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    public function reauthLogs(): HasMany
    {
        return $this->hasMany(SecurityReauthLog::class);
    }

    public function authAttemptLogs(): HasMany
    {
        return $this->hasMany(AuthAttemptLog::class, 'account_key', 'public_id');
    }

    public function houses(): BelongsToMany
    {
        return $this->belongsToMany(House::class)
            ->withPivot(['id', 'role', 'joined_at'])
            ->withTimestamps();
    }
}
