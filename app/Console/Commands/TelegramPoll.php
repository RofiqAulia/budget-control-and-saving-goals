<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Services\TelegramBot;
use Illuminate\Console\Command;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--once : Process any pending updates, then exit}';

    protected $description = 'Poll Telegram for bot updates (use this instead of a webhook when the app is not publicly reachable)';

    public function handle(TelegramBot $bot): int
    {
        $integration = Integration::query()
            ->where('provider', Integration::TELEGRAM)
            ->where('is_active', true)
            ->with('user')
            ->first();

        if (! $integration || ! $integration->user) {
            $this->error('No active Telegram integration. Save a bot token on the Integrations page first.');

            return self::FAILURE;
        }

        $token = (string) $integration->configValue('bot_token', '');

        if ($token === '') {
            $this->error('The Telegram integration has no bot token.');

            return self::FAILURE;
        }

        $me = $bot->getMe($token);

        if (! $me['ok']) {
            $this->error('Telegram rejected the token: '.$me['message']);

            return self::FAILURE;
        }

        $once = (bool) $this->option('once');

        $this->info("Listening as @{$me['username']} for {$integration->user->email}.");
        $this->line('Send /start to the bot in Telegram, then press Ctrl+C here to stop.');

        $offset = 0;

        while (true) {
            $result = $bot->getUpdates($token, $offset, $once ? 0 : 25);

            if (! $result['ok']) {
                $this->error($result['message']);

                if ($once) {
                    return self::FAILURE;
                }

                sleep(3);

                continue;
            }

            foreach ($result['updates'] as $update) {
                $offset = max($offset, ((int) ($update['update_id'] ?? 0)) + 1);

                $chatId = data_get($update, 'message.chat.id')
                    ?? data_get($update, 'edited_message.chat.id');

                // First message from a new chat becomes the default recipient.
                if ($chatId && ! $integration->configValue('chat_id')) {
                    $config = $integration->config ?? [];
                    $config['chat_id'] = (string) $chatId;

                    $integration->update(['config' => $config]);

                    $this->line("Captured chat_id {$chatId}.");
                }

                // Keyboard taps: handle them, then move on (they edit a message).
                if (isset($update['callback_query'])) {
                    $bot->handleCallback($update['callback_query'], $integration->user, $token);
                    $this->line("Handled #{$update['update_id']}: callback");

                    continue;
                }

                $reply = $bot->handleUpdate($update, $integration->user, $token);

                if ($reply && $chatId) {
                    $bot->send($token, $chatId, $reply);
                }

                $text = data_get($update, 'message.text') ?? data_get($update, 'edited_message.text') ?? '(non-text)';
                $this->line("Handled #{$update['update_id']}: {$text}");
            }

            if ($once) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
