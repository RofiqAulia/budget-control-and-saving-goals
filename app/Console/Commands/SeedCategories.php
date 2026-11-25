<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CategoryTemplate;
use Illuminate\Console\Command;

class SeedCategories extends Command
{
    protected $signature = 'app:seed-categories {--user= : Only this email}';

    protected $description = 'Create any categories missing from the standard template (safe to run repeatedly)';

    public function handle(): int
    {
        $query = User::query();

        if ($email = $this->option('user')) {
            $query->where('email', $email);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error('No matching user.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $before = $user->categories()->count();

            CategoryTemplate::seedFor($user);

            $added = $user->categories()->count() - $before;

            $this->line("{$user->email}: {$added} category(ies) added.");
        }

        return self::SUCCESS;
    }
}
