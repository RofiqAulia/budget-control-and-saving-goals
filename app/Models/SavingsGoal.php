<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsGoal extends Model
{
    protected $fillable = [
        'user_id', 'name', 'target_amount', 'saved_amount', 'target_date', 'note', 'is_completed',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'saved_amount' => 'decimal:2',
        'target_date' => 'date',
        'is_completed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->target_amount <= 0) {
            return 0.0;
        }

        return min(100, round(((float) $this->saved_amount / (float) $this->target_amount) * 100, 1));
    }

    public function getRemainingAttribute(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->saved_amount);
    }
}
