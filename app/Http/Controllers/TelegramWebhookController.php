<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramBot $bot): JsonResponse
    {
        // The config column is encrypted, so the secret cannot be matched with a
        // JSON path query — compare it in PHP after decryption instead.
        $integration = Integration::query()
            ->where('provider', Integration::TELEGRAM)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->first(fn (Integration $candidate) => hash_equals(
                (string) $candidate->configValue('webhook_secret', ''),
                $secret
            ));

        // Unknown secret: pretend nothing is here.
        abort_unless($integration && $integration->user, 404);

        $update = $request->all();
        $reply = $bot->handleUpdate($update, $integration->user);

        $chatId = data_get($update, 'message.chat.id')
            ?? data_get($update, 'edited_message.chat.id');

        if ($reply && $chatId) {
            $bot->send((string) $integration->configValue('bot_token'), $chatId, $reply);
        }

        return response()->json(['ok' => true]);
    }
}
