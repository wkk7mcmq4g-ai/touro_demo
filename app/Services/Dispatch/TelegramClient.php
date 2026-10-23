<?php

namespace App\Services\Dispatch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends a Telegram message to a driver, as PHPRunner's New_Button88: split into 4096-character
 * parts, Markdown, and logged in telegram_messages (direction "out").
 */
class TelegramClient
{
    /** @return int the Telegram message id of the last part */
    public function send(int $chatId, string $text, ?int $recipientId = null, ?string $date = null): int
    {
        if (! config('services.telegram.enabled')) {
            throw new RuntimeException('Telegram is switched off on this copy of the app (TELEGRAM_ENABLED=false in .env).');
        }
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not set in .env.');
        }

        $messageId = 0;
        foreach (mb_str_split($text, 4096) as $part) {
            $response = Http::asForm()->timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId, 'text' => $part, 'parse_mode' => 'Markdown',
            ]);
            if ($response->failed() || ! $response->json('ok')) {
                throw new RuntimeException('Telegram refused the message: '.($response->json('description') ?? 'HTTP '.$response->status()));
            }
            $messageId = (int) $response->json('result.message_id');
        }

        DB::table('telegram_messages')->insert([
            'direction' => 'out', 'chat_id' => $chatId, 'user_name' => 'System', 'message' => $text,
            'telegram_date' => now(), 'telegram_msg_id' => $messageId ?: null, 'tid' => $recipientId, 'tdate' => $date,
        ]);

        return $messageId;
    }
}
