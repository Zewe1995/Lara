<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Http;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $gapApiKey = config('services.gap.key');

        $update = Telegram::getWebhookUpdate();

        $message = $update->getMessage();

        if (!$message || !$message->text) {
            return response('No message', 200);
        }

        $chatId = $message->chat->id;
        $messageId = $message->messageId;
        $text = $message->text;

        // ارسال اکشن تایپینگ
        Telegram::sendChatAction([
            'chat_id' => $chatId,
            'action' => 'typing',
        ]);

        // آماده‌سازی پیام برای GPT
        $prompt = "لطفاً این متن را به فارسی ترجمه کن:\n\n" . $text;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $gapApiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.gapgpt.app/v1/chat/completions', [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000,
        ]);

        $reply = 'متاسفم، مشکلی پیش آمده.';

        if ($response->successful()) {
            $data = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? $reply;
        }

        // ارسال پاسخ
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'reply_to_message_id' => $messageId,
            'parse_mode' => 'Markdown',
        ]);

        return response('OK', 200);
    }
}
