<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Services\DebtSettlement;
use Illuminate\Console\Command;

class SyncDebts extends Command
{
    protected $signature = 'app:sync-debts {--force : Apply without asking}';

    protected $description = 'Link old debts to their categories and settle past Debts-category expenses';

    public function handle(): int
    {
        // 1. Debts created before the category picker only carry a name.
        $linked = 0;

        foreach (Category::whereNull('parent_id')->whereRaw('LOWER(name) = ?', ['debts'])->get() as $group) {
            $byName = $group->children()
                ->get()
                ->keyBy(fn (Category $child) => mb_strtolower($child->name));

            $legacy = Debt::forUser($group->user_id)->whereNull('category_id')->get();

            foreach ($legacy as $debt) {
                $category = $byName[mb_strtolower($debt->name)] ?? null;

                if ($category) {
                    $debt->update(['category_id' => $category->id]);
                    $this->line("  linked \"{$debt->name}\" → {$category->name}");
                    $linked++;
                }
            }
        }

        $this->info("Debts linked to a category: {$linked}");

        // 2. Expenses in a Debts category that were never applied to a debt.
        $alreadyApplied = DebtPayment::whereNotNull('transaction_id')->pluck('transaction_id')->all();

        $pending = Transaction::query()
            ->where('type', 'expense')
            ->whereNotNull('category_id')
            ->when($alreadyApplied !== [], fn ($q) => $q->whereNotIn('id', $alreadyApplied))
            ->get()
            ->filter(fn (Transaction $tx) => DebtSettlement::isDebtCategory($tx->category_id));

        if ($pending->isEmpty()) {
            $this->info('No unapplied Debts expenses.');

            return self::SUCCESS;
        }

        $total = (float) $pending->sum('amount');
        $this->warn("{$pending->count()} expense(s) totalling Rp".number_format($total, 0, ',', '.').' can be applied to debts.');

        foreach ($pending->take(10) as $tx) {
            $this->line("  #{$tx->id} {$tx->date->format('d/m/Y')} — {$tx->category?->name} — Rp".number_format((float) $tx->amount, 0, ',', '.'));
        }

        if ($pending->count() > 10) {
            $this->line('  … and '.($pending->count() - 10).' more');
        }

        if (! $this->option('force') && ! $this->confirm('Apply these to the matching debts?', true)) {
            $this->line('Nothing applied.');

            return self::SUCCESS;
        }

        foreach ($pending as $tx) {
            DebtSettlement::apply($tx);
        }

        $this->info('Done. Debt balances updated.');

        return self::SUCCESS;
    }
}
