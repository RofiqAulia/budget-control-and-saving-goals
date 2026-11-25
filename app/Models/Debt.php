<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    public const DEBT = 'debt';

    public const RECEIVABLE = 'receivable';

    protected $fillable = [
        'user_id', 'category_id', 'name', 'type', 'principal_amount', 'remaining_amount',
        'interest_rate', 'minimum_payment', 'due_date', 'note', 'is_settled',
    ];

    protected $casts = [
        'principal_amount' => 'float',
        'remaining_amount' => 'float',
        'interest_rate' => 'float',
        'minimum_payment' => 'float',
        'due_date' => 'date',
        'is_settled' => 'boolean',
    ];

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where($query->qualifyColumn('user_id'), $userId);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class)->orderByDesc('paid_on')->orderByDesc('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getIsReceivableAttribute(): bool
    {
        return $this->type === self::RECEIVABLE;
    }

    public function getPaidAmountAttribute(): float
    {
        return max(0.0, $this->principal_amount - $this->remaining_amount);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->principal_amount <= 0) {
            return $this->is_settled ? 100 : 0;
        }

        return (int) min(100, round(($this->paid_amount / $this->principal_amount) * 100));
    }

    /**
     * Totals used by the portfolio and savings pages.
     *
     * @return array{payable: float, receivable: float, net: float, monthly: float, settled: int}
     */
    public static function summaryFor(int $userId): array
    {
        $rows = static::forUser($userId)
            ->where('is_settled', false)
            ->selectRaw('type, SUM(remaining_amount) as total, SUM(COALESCE(minimum_payment, 0)) as monthly')
            ->groupBy('type')
            ->get();

        $payable = (float) ($rows->firstWhere('type', self::DEBT)->total ?? 0);
        $receivable = (float) ($rows->firstWhere('type', self::RECEIVABLE)->total ?? 0);

        return [
            'payable' => $payable,
            'receivable' => $receivable,
            'net' => $payable - $receivable,
            'monthly' => (float) $rows->sum('monthly'),
            'settled' => static::forUser($userId)->where('is_settled', true)->count(),
        ];
    }
}
