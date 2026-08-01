<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class AuthAttemptLog extends Model
{
    protected $fillable = [
        'type',
        'account_key',
        'ip',
        'is_success',
    ];

    protected function casts(): array
    {
        return [
            'is_success' => 'boolean',
        ];
    }

    public function setAccountKeyAttribute(?string $value): void
    {
        $this->attributes['account_key'] = $value === null ? null : mb_strtolower(trim($value));
    }
}
