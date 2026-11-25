<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Holding;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a demo account whose numbers reproduce the sample spreadsheet exactly.
     *
     * Local only: this creates sample transactions, so it must never touch a
     * production database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DatabaseSeeder is demo data and is disabled in production. Use `php artisan app:reset` instead.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'demo@finance.app'],
            ['name' => 'Demo User', 'password' => 'password']
        );

        CategoryTemplate::seedFor($user);

        $categories = Category::forUser($user->id)
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy('name');

        $leaf = fn (string $name) => $categories->get($name)?->id;

        // Leaf-level amounts for the current month, taken from the sample report.
        $baseExpenses = [
            'Kost' => 700_000,
            'Makan' => 900_000,
            'Internet' => 120_000,
            'Bensin' => 200_000,
            'Kopi+Gula' => 77_000,
            'Service' => 66_667,
            'Ganti Oli' => 80_000,
            'E-Money' => 20_000,
            'Deterjen' => 10_000,
            'Pewangi' => 10_000,
            'Parfum' => 75_000,
            'Facial wash' => 35_000,
            'Serum (opt)' => 45_000,
            'Sunscreen' => 40_000,
            'Hair Tonic' => 30_000,
            'Deodorant' => 20_000,
            'Parfum ruangan' => 25_000,
            'Body wash' => 10_000,
            'Sikat + Pasta' => 15_000,
            'Shampo' => 20_000,
            'Tissue' => 20_000,
        ];

        $now = Carbon::now()->startOfMonth();

        // Build six months of history so the dashboard has a real trend line.
        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $month = $now->copy()->subMonths($monthsAgo);

            // Older months scale slightly down; current month is exact.
            $factor = $monthsAgo === 0 ? 1 : 0.86 + (5 - $monthsAgo) * 0.025;

            Transaction::create([
                'user_id' => $user->id,
                'category_id' => $leaf('Magang'),
                'type' => 'income',
                'date' => $month->copy()->day(25)->toDateString(),
                'amount' => 5_400_000,
                'description' => 'Gaji magang '.$month->format('F Y'),
                'source' => 'web',
            ]);

            if ($monthsAgo > 0) {
                Transaction::create([
                    'user_id' => $user->id,
                    'category_id' => $leaf('ShopeeFood'),
                    'type' => 'income',
                    'date' => $month->copy()->day(12)->toDateString(),
                    'amount' => 180_000 + $monthsAgo * 35_000,
                    'description' => 'ShopeeFood driver',
                    'source' => 'telegram',
                ]);
            }

            if ($monthsAgo > 1) {
                Transaction::create([
                    'user_id' => $user->id,
                    'category_id' => $leaf('Dividen'),
                    'type' => 'income',
                    'date' => $month->copy()->day(8)->toDateString(),
                    'amount' => 45_000 + $monthsAgo * 12_000,
                    'description' => 'Dividen BBCA',
                    'source' => 'sheets',
                ]);
            }

            foreach ($baseExpenses as $name => $amount) {
                if ($monthsAgo > 0 && $name === 'Ganti Oli' && $monthsAgo % 3 !== 0) {
                    continue; // serviced every few months, not every month
                }

                Transaction::create([
                    'user_id' => $user->id,
                    'category_id' => $leaf($name),
                    'type' => 'expense',
                    'date' => $month->copy()->day(min(28, 3 + (int) (crc32($name) % 24)))->toDateString(),
                    'amount' => (int) round($amount * $factor),
                    'description' => $name,
                    'source' => $monthsAgo === 0 ? 'web' : 'sheets',
                ]);
            }

            if ($monthsAgo === 0) {
                Transaction::create([
                    'user_id' => $user->id,
                    'category_id' => $leaf('Self Reward'),
                    'type' => 'expense',
                    'date' => $month->copy()->day(20)->toDateString(),
                    'amount' => 150_000,
                    'description' => 'Self reward bulanan',
                    'source' => 'telegram',
                ]);
            }
        }

        // Budgets for the current month, sized around actual spending.
        $budgetPlan = [
            'Pokok' => 2_200_000,
            'Transportation' => 250_000,
            'Shopping Bulanan' => 400_000,
            'Shopping Sekunder' => 300_000,
        ];

        foreach ($budgetPlan as $groupName => $amount) {
            $group = Category::forUser($user->id)->roots()->where('name', $groupName)->first();

            Budget::updateOrCreate(
                ['user_id' => $user->id, 'category_id' => $group?->id, 'period' => $now->format('Y-m')],
                ['amount' => $amount]
            );
        }

        SavingsGoal::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Dana Darurat'],
            ['target_amount' => 18_000_000, 'saved_amount' => 6_500_000, 'target_date' => $now->copy()->addMonths(10), 'note' => '6x pengeluaran bulanan']
        );

        SavingsGoal::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Laptop Baru'],
            ['target_amount' => 12_000_000, 'saved_amount' => 4_200_000, 'target_date' => $now->copy()->addMonths(6)]
        );

        SavingsGoal::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Liburan Bali'],
            ['target_amount' => 5_000_000, 'saved_amount' => 1_750_000, 'target_date' => $now->copy()->addMonths(4)]
        );

        $holdings = [
            ['BBCA', 'Bank Central Asia', 'stock', 12, 9_200, 10_150, 0],
            ['BBRI', 'Bank Rakyat Indonesia', 'stock', 20, 4_600, 4_420, 0],
            ['GOTO', 'GoTo Gojek Tokopedia', 'stock', 500, 62, 58, 0],
            ['BTC', 'Bitcoin', 'crypto', 0.0035, 950_000_000, 1_020_000_000, 0],
            ['EMAS', 'Emas Antam 1gr', 'gold', 2, 1_350_000, 1_480_000, 0],
            ['RDPU', 'Reksa Dana Pasar Uang', 'mutual_fund', 1, 1_500_000, 1_568_000, 45_000],
        ];

        foreach ($holdings as [$symbol, $name, $type, $qty, $avg, $current, $dividend]) {
            Holding::firstOrCreate(
                ['user_id' => $user->id, 'symbol' => $symbol],
                [
                    'name' => $name,
                    'asset_type' => $type,
                    'quantity' => $qty,
                    'avg_price' => $avg,
                    'current_price' => $current,
                    'dividend_received' => $dividend,
                ]
            );
        }

        $this->command?->info('Demo account ready: demo@finance.app / password');
    }
}
