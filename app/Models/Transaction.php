<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'type', 'date', 'amount', 'description', 'source', 'external_id', 'meta',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public const SOURCES = ['web', 'telegram', 'sheets', 'import'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        // Qualified so the scope still works when categories are joined for sorting.
        return $query->where($query->qualifyColumn('user_id'), $userId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where($query->qualifyColumn('type'), $type);
    }

    public function scopeInPeriod(Builder $query, string $period): Builder
    {
        [$year, $month] = explode('-', $period);

        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
