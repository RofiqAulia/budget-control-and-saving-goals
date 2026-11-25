<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'config', 'is_active', 'last_synced_at', 'last_error',
    ];

    protected $casts = [
        'config' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public const TELEGRAM = 'telegram';

    public const GOOGLE_SHEETS = 'google_sheets';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forProvider(int $userId, string $provider): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'provider' => $provider],
            ['config' => [], 'is_active' => false]
        );
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }
}
