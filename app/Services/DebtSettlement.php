<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;

/**
 * Keeps debts in sync with expenses recorded under a Debts category.
 *
 * Spending Rp500.000 on "Debts / Saudara" means that debt was paid down by
 * Rp500.000, so the remaining balance, progress bar, composition and total all
 * move together. Reverting the expense puts the balance back.
 */
class DebtSettlement
{
    /**
     * Apply an expense to the debts that share its category, oldest due first.
     */
    public static function apply(Transaction $transaction): void
    {
        if ($transaction->type !== 'expense' || ! $transaction->category_id) {
            return;
        }

        if (! self::isDebtCategory($transaction->category_id)) {
            return;
        }

        $left = (float) $transaction->amount;

        if ($left <= 0) {
            return;
        }

        $debts = Debt::forUser($transaction->user_id)
            ->where('category_id', $transaction->category_id)
            ->where('is_settled', false)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($debts as $debt) {
            if ($left <= 0) {
                break;
            }

            $applied = min($left, (float) $debt->remaining_amount);

            if ($applied <= 0) {
                continue;
            }

            $remaining = round((float) $debt->remaining_amount - $applied, 2);

            $debt->update([
                'remaining_amount' => $remaining,
                'is_settled' => $remaining <= 0,
            ]);

            $debt->payments()->create([
                'transaction_id' => $transaction->id,
                'amount' => $applied,
                'paid_on' => $transaction->date,
                'note' => $transaction->description ?: null,
            ]);

            $left = round($left - $applied, 2);
        }
    }

    /**
     * Undo whatever this expense applied, before it is edited or deleted.
     */
    public static function revert(Transaction $transaction): void
    {
        $payments = DebtPayment::query()
            ->where('transaction_id', $transaction->id)
            ->with('debt')
            ->get();

        foreach ($payments as $payment) {
            if ($payment->debt) {
                $payment->debt->update([
                    'remaining_amount' => round((float) $payment->debt->remaining_amount + (float) $payment->amount, 2),
                    'is_settled' => false,
                ]);
            }

            $payment->delete();
        }
    }

    /**
     * True when the category sits under the "Debts" group.
     */
    public static function isDebtCategory(int $categoryId): bool
    {
        $category = Category::with('parent')->find($categoryId);

        if (! $category) {
            return false;
        }

        $groupName = $category->parent?->name ?? $category->name;

        return mb_strtolower($groupName) === 'debts';
    }
}
