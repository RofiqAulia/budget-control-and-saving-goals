<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holding extends Model
{
    protected $fillable = [
        'user_id', 'symbol', 'name', 'asset_type', 'quantity', 'avg_price', 'current_price', 'dividend_received', 'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'avg_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'dividend_received' => 'decimal:2',
    ];

    public const ASSET_TYPES = ['stock', 'mutual_fund', 'crypto', 'gold', 'bond', 'cash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function getPriceAttribute(): float
    {
        return (float) ($this->current_price ?? $this->avg_price);
    }

    public function getMarketValueAttribute(): float
    {
        return (float) $this->quantity * $this->price;
    }

    public function getCostBasisAttribute(): float
    {
        return (float) $this->quantity * (float) $this->avg_price;
    }

    public function getGainAttribute(): float
    {
        return $this->market_value - $this->cost_basis;
    }

    public function getGainPercentAttribute(): float
    {
        if ($this->cost_basis <= 0) {
            return 0.0;
        }

        return round(($this->gain / $this->cost_basis) * 100, 2);
    }
}
