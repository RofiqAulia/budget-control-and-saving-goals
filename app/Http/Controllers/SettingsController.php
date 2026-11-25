<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\GoogleSheetsService;
use App\Services\TelegramBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $telegram = Integration::forProvider($request->user()->id, Integration::TELEGRAM);
        $sheets = Integration::forProvider($request->user()->id, Integration::GOOGLE_SHEETS);

        $secret = $telegram->configValue('webhook_secret');

        return view('settings.index', [
            'telegram' => $telegram,
            'sheets' => $sheets,
            'webhookUrl' => $secret ? url("/telegram/webhook/{$secret}") : null,
            'appUrlIsLocal' => in_array(parse_url(config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true),
        ]);
    }

    public function updateTelegram(Request $request, TelegramBot $bot): RedirectResponse
    {
        $data = $request->validate([
            'bot_token' => ['required', 'string', 'max:200'],
            'chat_id' => ['nullable', 'string', 'max:40'],
        ]);

        $integration = Integration::forProvider($request->user()->id, Integration::TELEGRAM);
        $config = $integration->config ?? [];

        $config['bot_token'] = trim($data['bot_token']);
        $config['chat_id'] = isset($data['chat_id']) ? trim($data['chat_id']) : null;
        $config['webhook_secret'] = $config['webhook_secret'] ?? Str::random(40);

        $integration->update([
            'config' => $config,
            'is_active' => true,
        ]);

        // Verify the token and register the webhook in one go.
        $me = $bot->getMe($config['bot_token']);

        if (! $me['ok']) {
            $integration->update(['last_error' => $me['message']]);

            return back()->with('error', 'Telegram rejected the token: '.$me['message']);
        }

        $webhook = $bot->setWebhook($config['bot_token'], url("/telegram/webhook/{$config['webhook_secret']}"));

        $integration->update([
            'last_error' => $webhook['ok'] ? null : $webhook['message'],
        ]);

        $status = "Bot @{$me['username']} connected.";

        if (! $webhook['ok']) {
            $status .= ' Webhook not registered: '.$webhook['message'];
        }

        return back()->with($webhook['ok'] ? 'status' : 'error', $status);
    }

    public function testTelegram(Request $request, TelegramBot $bot): RedirectResponse
    {
        $integration = Integration::forProvider($request->user()->id, Integration::TELEGRAM);
        $token = (string) $integration->configValue('bot_token', '');
        $chatId = (string) $integration->configValue('chat_id', '');

        if ($token === '') {
            return back()->with('error', 'Save a bot token first.');
        }

        $me = $bot->getMe($token);

        if (! $me['ok']) {
            return back()->with('error', 'Token check failed: '.$me['message']);
        }

        if ($chatId === '') {
            return back()->with('status', "Bot @{$me['username']} is valid. Add a chat ID to receive a test message.");
        }

        $sent = $bot->send($token, $chatId, 'Finora connected. Try: makan 25000');

        return back()->with(
            $sent ? 'status' : 'error',
            $sent ? 'Test message sent.' : 'Telegram refused to send the message. Check the chat ID and that you started the bot.'
        );
    }

    public function updateSheets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'credentials' => ['required', 'string'],
            'spreadsheet_id' => ['required', 'string', 'max:200'],
            'sheet_name' => ['nullable', 'string', 'max:80'],
        ]);

        $decoded = json_decode($data['credentials'], true);

        if (! is_array($decoded) || ! isset($decoded['client_email'], $decoded['private_key'])) {
            return back()->with('error', 'That JSON is missing client_email or private_key.');
        }

        $integration = Integration::forProvider($request->user()->id, Integration::GOOGLE_SHEETS);

        $integration->update([
            'config' => [
                'credentials' => $data['credentials'],
                'spreadsheet_id' => trim($data['spreadsheet_id']),
                'sheet_name' => $data['sheet_name'] ?: null,
                'service_account_email' => $decoded['client_email'],
            ],
            'is_active' => true,
            'last_error' => null,
        ]);

        try {
            $service = new GoogleSheetsService(
                $data['credentials'],
                trim($data['spreadsheet_id']),
                $data['sheet_name'] ?: null
            );

            $result = $service->test();
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $integration->update(['last_error' => $result['ok'] ? null : $result['message']]);

        return back()->with(
            $result['ok'] ? 'status' : 'error',
            $result['ok'] ? 'Connected to "'.$result['message'].'"' : $result['message']
        );
    }
}
