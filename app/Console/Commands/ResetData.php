<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\User;
use App\Services\CategoryTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetData extends Command
{
    protected $signature = 'app:reset
                            {--email= : Email for the account to create}
                            {--password= : Password for that account}
                            {--name= : Display name (defaults to the part before @)}
                            {--wipe-integrations : Also drop Telegram / Google Sheets credentials}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe all data, create one account, and seed the standard category tree';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        $password = (string) $this->option('password');
        $name = trim((string) ($this->option('name') ?: str($email)->before('@')->replace(['.', '_'], ' ')->title()));

        if ($email === '' || $password === '') {
            $this->error('Both --email and --password are required.');

            return self::FAILURE;
        }

        $this->warn('This deletes every user, transaction, budget, goal, holding and debt.');

        if (! $this->option('force') && ! $this->confirm("Wipe all data and create {$email}?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        // Integrations are configuration, not data — keep them unless asked otherwise.
        $preserved = [];

        if (! $this->option('wipe-integrations')) {
            $preserved = Integration::query()->get()->map(fn (Integration $i) => [
                'provider' => $i->provider,
                'config' => $i->config,
                'is_active' => $i->is_active,
                'last_synced_at' => $i->last_synced_at,
                'last_error' => $i->last_error,
            ])->all();
        }

        $user = DB::transaction(function () use ($email, $password, $name, $preserved) {
            // Foreign keys cascade, so this clears every table owned by a user.
            User::query()->delete();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            CategoryTemplate::seedFor($user);

            foreach ($preserved as $row) {
                Integration::create($row + ['user_id' => $user->id]);
            }

            return $user;
        });

        $this->info("Done. Account {$user->email} created with the standard category tree.");

        if ($preserved !== []) {
            $this->line('Kept '.count($preserved).' integration(s) and moved them to the new account.');
        }

        return self::SUCCESS;
    }
}
