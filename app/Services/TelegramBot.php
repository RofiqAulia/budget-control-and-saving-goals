<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DebtSettlement;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBot
{
    public const API = 'https://api.telegram.org/bot';

    /**
     * Handle one Telegram update and return the reply text (or null for no reply).
     *
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update, User $user, ?string $token = null): ?string
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! $message || ! isset($message['text'])) {
            return null;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) $message['text']);

        if (! $chatId) {
            return null;
        }

        $isCommand = str_starts_with($text, '/');

        // A category was picked from the keyboard; the next text is the amount.
        $pending = Cache::get($this->pendingKey($chatId));

        if ($pending && ! $isCommand) {
            return $this->completePending($pending, $text, $user, (string) $chatId, $message['message_id'] ?? null);
        }

        if ($isCommand) {
            Cache::forget($this->pendingKey($chatId));

            $command = strtolower(explode(' ', $text)[0]);
            $type = match ($command) {
                '/expense', '/pengeluaran', '/keluar' => 'expense',
                '/income', '/pemasukan', '/masuk' => 'income',
                default => null,
            };

            if ($type) {
                [$keyboardText, $keyboard] = $this->groupsView($user, $type);

                if ($token && $keyboard !== []) {
                    $this->sendKeyboard($token, $chatId, $keyboardText, $keyboard);

                    return null;
                }

                return $keyboardText;
            }

            return $this->handleCommand($text, $user);
        }

        return $this->handleTransaction($text, $user, (string) $chatId, $message['message_id'] ?? null);
    }

    private function pendingKey(string|int $chatId): string
    {
        return "tg:pending:{$chatId}";
    }

    /**
     * Finish the keyboard flow: the pending category plus the amount just typed.
     */
    private function completePending(array $pending, string $text, User $user, string $chatId, ?int $messageId): string
    {
        $parsed = self::parse($text);

        if (! $parsed) {
            return 'Send the amount as a number, e.g. 25000. Use /batal to cancel.';
        }

        $category = Category::forUser($user->id)->whereKey($pending['category_id'] ?? 0)->first();

        if (! $category) {
            Cache::forget($this->pendingKey($chatId));

            return 'That category no longer exists. Send /expense to start again.';
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => $category->type,
            'date' => Carbon::now()->toDateString(),
            'amount' => $parsed['amount'],
            'description' => $parsed['category'] ?: $category->name,
            'source' => 'telegram',
            'external_id' => $messageId ? "tg:{$chatId}:{$messageId}" : null,
            'meta' => ['raw' => $text, 'chat_id' => $chatId, 'via' => 'keyboard'],
        ]);

        DebtSettlement::apply($transaction);

        Cache::forget($this->pendingKey($chatId));

        $label = $category->parent ? "{$category->parent->name} / {$category->name}" : $category->name;

        return ucfirst($category->type).' saved: '.Money::format($transaction->amount).' → '.$label;
    }

    /**
     * Inline keyboard navigation: groups → categories → amount.
     */
    public function handleCallback(array $callback, User $user, string $token): void
    {
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $data = (string) ($callback['data'] ?? '');

        $this->answerCallback($token, (string) ($callback['id'] ?? ''));

        if (! $chatId || ! $messageId) {
            return;
        }

        [$action, $type, $id] = array_pad(explode(':', $data), 3, null);
        $type = in_array($type, ['income', 'expense'], true) ? $type : 'expense';

        if ($action === 'g') {
            $group = Category::forUser($user->id)->ofType($type)->roots()->whereKey((int) $id)->first();

            if (! $group) {
                return;
            }

            $this->editMessage($token, $chatId, $messageId, "Pick a category in {$group->name}:", $this->subcategoryKeyboard($group));

            return;
        }

        if ($action === 'c') {
            $category = Category::forUser($user->id)->ofType($type)->whereKey((int) $id)->first();

            if (! $category) {
                return;
            }

            Cache::put(
                $this->pendingKey($chatId),
                ['category_id' => $category->id],
                now()->addMinutes(10)
            );

            $label = $category->parent ? "{$category->parent->name} / {$category->name}" : $category->name;

            $this->editMessage(
                $token,
                $chatId,
                $messageId,
                "Send the amount for {$label}.\nExample: 25000",
                [[['text' => 'Cancel', 'callback_data' => 'x']]]
            );

            return;
        }

        if ($action === 'b') {
            [$keyboardText, $keyboard] = $this->groupsView($user, $type);
            $this->editMessage($token, $chatId, $messageId, $keyboardText, $keyboard);

            return;
        }

        if ($action === 'x') {
            Cache::forget($this->pendingKey($chatId));
            $this->editMessage($token, $chatId, $messageId, 'Cancelled.');
        }
    }

    /**
     * @return array{0: string, 1: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function groupsView(User $user, string $type): array
    {
        $groups = Category::forUser($user->id)
            ->ofType($type)
            ->roots()
            ->orderBy('sort_order')
            ->get();

        if ($groups->isEmpty()) {
            return ['No '.$type.' categories yet. Create them on the web first.', []];
        }

        $rows = $groups
            ->map(fn (Category $group) => ['text' => $group->name, 'callback_data' => "g:{$type}:{$group->id}"])
            ->chunk(2)
            ->map(fn ($chunk) => $chunk->values()->all())
            ->values()
            ->all();

        return [($type === 'income' ? 'Income' : 'Expense').' — pick a group:', $rows];
    }

    /**
     * @return array<int, array<int, array{text: string, callback_data: string}>>
     */
    private function subcategoryKeyboard(Category $group): array
    {
        $rows = $group->children()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $child) => ['text' => $child->name, 'callback_data' => "c:{$group->type}:{$child->id}"])
            ->chunk(2)
            ->map(fn ($chunk) => $chunk->values()->all())
            ->values()
            ->all();

        $rows[] = [['text' => '← Back', 'callback_data' => "b:{$group->type}"]];

        return $rows;
    }

    private function handleCommand(string $text, User $user): string
    {
        $command = strtolower(explode(' ', $text)[0]);
        $report = ReportService::for($user->id);
        $period = Carbon::now()->format('Y-m');

        return match ($command) {
            '/start', '/help' => implode("\n", [
                'Finora bot — log a transaction by typing it, or tap a category.',
                '',
                'Shortcuts:',
                '  /expense — pick an expense category with buttons',
                '  /income  — pick an income category with buttons',
                '',
                'Or type it:',
                '  makan 25000',
                '  kopi 15rb',
                '  bensin 20k',
                '  income shopee 180000',
                '',
                'Commands:',
                '  /saldo — this month summary',
                '  /budget — budget status',
                '  /laporan — link to the web report',
            ]),
            '/saldo', '/summary' => $this->summaryText($user, $period),
            '/budget' => $this->budgetText($user, $period),
            '/laporan', '/report' => 'Open your report: '.url('/reports?period='.$period),
            default => 'Unknown command. Try /help.',
        };
    }

    private function summaryText(User $user, string $period): string
    {
        $summary = ReportService::for($user->id)->monthlySummary($period);
        $label = Carbon::parse($period.'-01')->format('F Y');

        return implode("\n", [
            "Summary — {$label}",
            'Income:  '.Money::format($summary['income']),
            'Expense: '.Money::format($summary['expense']),
            'Net:     '.Money::format($summary['net']),
            'Savings rate: '.$summary['savings_rate'].'%',
        ]);
    }

    private function budgetText(User $user, string $period): string
    {
        $overview = ReportService::for($user->id)->budgetOverview($period);

        if (empty($overview['rows'])) {
            return 'No budgets set for '.Carbon::parse($period.'-01')->format('F Y').'. Set them on the web app.';
        }

        $lines = ['Budget — '.Carbon::parse($period.'-01')->format('F Y')];

        foreach ($overview['rows'] as $row) {
            $name = $row['category']?->name ?? 'Overall';
            $flag = $row['over'] ? ' ⚠ over' : '';
            $lines[] = sprintf('%s: %s / %s (%s%%)%s', $name, Money::format($row['spent']), Money::format($row['amount']), $row['percent'], $flag);
        }

        return implode("\n", $lines);
    }

    private function handleTransaction(string $text, User $user, string $chatId, ?int $messageId): string
    {
        $parsed = self::parse($text);

        if (! $parsed) {
            return "I couldn't find an amount in that message.\nTry: makan 25000";
        }

        $category = $this->resolveCategory($user, $parsed['category'], $parsed['type']);

        if (! $category) {
            return "I couldn't match \"{$parsed['category']}\" to a category.\n"
                .'Available: '.$this->categoryHints($user);
        }

        // A category determines the direction when the message didn't state one.
        $type = $parsed['explicit_type'] ? $parsed['type'] : $category->type;

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => $type,
            'date' => Carbon::now()->toDateString(),
            'amount' => $parsed['amount'],
            'description' => $parsed['category'] ?: $category->name,
            'source' => 'telegram',
            'external_id' => $messageId ? "tg:{$chatId}:{$messageId}" : null,
            'meta' => ['raw' => $text, 'chat_id' => $chatId],
        ]);

        DebtSettlement::apply($transaction);

        $label = $category->parent ? "{$category->parent->name} / {$category->name}" : $category->name;

        return ucfirst($type).' saved: '.Money::format($transaction->amount).' → '.$label;
    }

    /**
     * Parse free text such as "makan 25000", "kopi 15rb", "income gaji 5.400.000".
     *
     * @return array{amount: float, category: string, type: string, explicit_type: bool}|null
     */
    public static function parse(string $text): ?array
    {
        $original = trim($text);
        $working = strtolower($original);

        $type = 'expense';
        $explicit = false;

        if (preg_match('/^(income|pemasukan|masuk|in)\b/u', $working, $m)) {
            $type = 'income';
            $explicit = true;
            $working = trim(substr($working, strlen($m[0])));
        } elseif (preg_match('/^(expense|pengeluaran|keluar|out)\b/u', $working, $m)) {
            $type = 'expense';
            $explicit = true;
            $working = trim(substr($working, strlen($m[0])));
        }

        // Amount: digits with optional thousand separators and a magnitude suffix.
        if (! preg_match_all('/(\d[\d.,]*)\s*(rb|ribu|k|jt|juta|m)?/iu', $working, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $best = null;
        $bestScore = -1;

        foreach ($matches as $match) {
            $raw = $match[1];
            $suffix = strtolower($match[2] ?? '');
            $value = Money::parse($raw);

            if ($value <= 0) {
                continue;
            }

            $value *= match ($suffix) {
                'rb', 'ribu', 'k' => 1000,
                'jt', 'juta', 'm' => 1_000_000,
                default => 1,
            };

            // Prefer suffixed numbers, then the largest value.
            $score = ($suffix !== '' ? 1_000_000_000 : 0) + $value;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['value' => $value, 'raw' => $match[0]];
            }
        }

        if (! $best) {
            return null;
        }

        $category = trim(preg_replace('/\s+/', ' ', str_replace($best['raw'], ' ', $working)));
        $category = trim($category, " \t\n\r\0\x0B.,-+");

        return [
            'amount' => (float) $best['value'],
            'category' => $category,
            'type' => $type,
            'explicit_type' => $explicit,
        ];
    }

    private function resolveCategory(User $user, string $query, string $type): ?Category
    {
        if ($query === '') {
            return null;
        }

        $needle = mb_strtolower($query);

        $categories = Category::forUser($user->id)->where('is_active', true)->with('parent')->get();

        // 1. Exact leaf/group name.
        $exact = $categories->first(fn (Category $c) => mb_strtolower($c->name) === $needle);
        if ($exact) {
            return $exact;
        }

        // 2. Name contains the query (or vice versa), preferring leaves.
        $partial = $categories
            ->filter(fn (Category $c) => str_contains(mb_strtolower($c->name), $needle)
                || str_contains($needle, mb_strtolower($c->name)))
            ->sortByDesc(fn (Category $c) => $c->parent_id !== null)
            ->first();

        if ($partial) {
            return $partial;
        }

        // 3. Group name typed directly — log against the group.
        return $categories->first(
            fn (Category $c) => $c->parent_id === null
                && str_contains($needle, mb_strtolower($c->name))
                && $c->type === $type
        );
    }

    private function categoryHints(User $user): string
    {
        return Category::forUser($user->id)
            ->whereNotNull('parent_id')
            ->orderBy('sort_order')
            ->limit(12)
            ->pluck('name')
            ->implode(', ');
    }

    /**
     * Send a message carrying an inline keyboard.
     *
     * @param  array<int, array<int, array{text: string, callback_data: string}>>  $keyboard
     */
    public function sendKeyboard(string $token, string|int $chatId, string $text, array $keyboard): bool
    {
        try {
            $response = Http::timeout(10)->post(self::API.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => ['inline_keyboard' => $keyboard],
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram sendMessage (keyboard) failed', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage (keyboard) error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Replace a keyboard message in place as the user drills down.
     *
     * @param  array<int, array<int, array{text: string, callback_data: string}>>|null  $keyboard
     */
    public function editMessage(string $token, string|int $chatId, int $messageId, string $text, ?array $keyboard = null): bool
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
            ];

            if ($keyboard !== null) {
                $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
            }

            $response = Http::timeout(10)->post(self::API.$token.'/editMessageText', $payload);

            // Tapping the same button twice returns "message is not modified"; that is fine.
            if (! $response->successful()) {
                $description = (string) ($response->json('description') ?? '');

                if (! str_contains($description, 'not modified')) {
                    Log::warning('Telegram editMessageText failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram editMessageText error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The ☰ command menu inside Telegram. Without this the bot looks empty.
     *
     * @return array<int, array{command: string, description: string}>
     */
    public static function commands(): array
    {
        return [
            ['command' => 'expense', 'description' => 'Catat pengeluaran — pilih kategori'],
            ['command' => 'income', 'description' => 'Catat pemasukan — pilih kategori'],
            ['command' => 'saldo', 'description' => 'Ringkasan bulan ini'],
            ['command' => 'budget', 'description' => 'Status budget'],
            ['command' => 'laporan', 'description' => 'Link laporan di web'],
            ['command' => 'help', 'description' => 'Cara pakai bot'],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function setCommands(string $token): array
    {
        try {
            $response = Http::timeout(10)->post(self::API.$token.'/setMyCommands', [
                'commands' => self::commands(),
            ]);

            $body = $response->json();

            return [
                'ok' => (bool) ($body['ok'] ?? false),
                'message' => $body['description'] ?? ($response->successful() ? 'Commands registered.' : 'Telegram rejected the command list.'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function answerCallback(string $token, string $callbackId, string $text = ''): void
    {
        try {
            Http::timeout(10)->post(self::API.$token.'/answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallbackQuery error: '.$e->getMessage());
        }
    }

    public function send(string $token, string|int $chatId, string $text): bool
    {
        try {
            $response = Http::timeout(10)->post(self::API.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram sendMessage failed', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function setWebhook(string $token, string $url): array
    {
        try {
            $response = Http::timeout(10)->post(self::API.$token.'/setWebhook', [
                'url' => $url,
                'allowed_updates' => ['message', 'edited_message', 'callback_query'],
            ]);

            $body = $response->json();

            return [
                'ok' => (bool) ($body['ok'] ?? false),
                'message' => $body['description'] ?? ($response->successful() ? 'Webhook registered.' : 'Telegram rejected the webhook.'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Long-poll for new updates. Used by `telegram:poll`, because Telegram
     * refuses to deliver a webhook to a localhost URL.
     *
     * @return array{ok: bool, updates: array, message: string}
     */
    public function getUpdates(string $token, int $offset = 0, int $timeout = 25): array
    {
        try {
            $response = Http::timeout($timeout + 10)->get(self::API.$token.'/getUpdates', [
                'offset' => $offset,
                'timeout' => $timeout,
                'allowed_updates' => ['message', 'edited_message', 'callback_query'],
            ]);

            $body = $response->json();

            if (! ($body['ok'] ?? false)) {
                return ['ok' => false, 'updates' => [], 'message' => $body['description'] ?? 'getUpdates failed.'];
            }

            return ['ok' => true, 'updates' => $body['result'] ?? [], 'message' => 'ok'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'updates' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, username: ?string, message: string}
     */
    public function getMe(string $token): array
    {
        try {
            $response = Http::timeout(10)->get(self::API.$token.'/getMe');
            $body = $response->json();

            if (! ($body['ok'] ?? false)) {
                return ['ok' => false, 'username' => null, 'message' => $body['description'] ?? 'Invalid token.'];
            }

            return ['ok' => true, 'username' => $body['result']['username'] ?? null, 'message' => 'Connected.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'username' => null, 'message' => $e->getMessage()];
        }
    }
}
