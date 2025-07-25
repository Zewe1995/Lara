<?php
// app/Http/Controllers/TelegramWebhookController.php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $update = Telegram::getWebhookUpdate();

        // 1. اگر کاربر روی دکمه شیشه‌ای کلیک کرده بود (Callback Query)
        if ($update->isType('callback_query')) {
            $this->handleCallbackQuery($update);
        }
        // 2. اگر پیام متنی ارسال کرده بود
        elseif ($update->getMessage() && $update->getMessage()->text) {
            $message = $update->getMessage();
            $chatId = $message->chat->id;
            $text = $message->text;

            // اگر پیام یک دستور بود (مثل /start یا /model)
            if (Str::startsWith($text, '/')) {
                $this->handleCommand($chatId, $text);
            }
            // در غیر این صورت، یک پیام عادی برای هوش مصنوعی است
            else {
                $this->handleTextMessage($chatId, $text, $message->messageId);
            }
        }

        return response('OK', 200);
    }

    /**
     * مدیریت دستورات مانند /model
     */
    private function handleCommand($chatId, $text)
    {
        if ($text === '/model') {
            $this->showModelSelection($chatId);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "دستور نامعتبر است. برای انتخاب مدل هوش مصنوعی از دستور /model استفاده کنید.",
            ]);
        }
    }

    /**
     * نمایش لیست مدل‌ها به صورت دکمه‌های شیشه‌ای
     */
    private function showModelSelection($chatId)
    {
        // خواندن مدل‌های فعال از دیتابیس
        $models = AiModel::where('is_active', true)->get();

        if ($models->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "متاسفانه در حال حاضر هیچ مدل فعالی وجود ندارد.",
            ]);
            return;
        }

        // ساخت دکمه‌ها (Keyboard)
        // برای زیبایی بیشتر، دکمه‌ها را در ردیف‌های دو تایی می‌چینیم
        $keyboard = $models->map(function ($model) {
            return [
                'text' => $model->title ?? $model->name, // اگر عنوان داشت نمایش بده، وگرنه نام مدل
                'callback_data' => 'set_model:' . $model->id // داده‌ای که با کلیک ارسال می‌شود
            ];
        })->chunk(2)->toArray();


        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "لطفاً مدل هوش مصنوعی مورد نظر خود را انتخاب کنید:",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }

    /**
     * مدیریت کلیک کاربر روی دکمه‌های انتخاب مدل
     */
    private function handleCallbackQuery($update)
    {
        $callbackQuery = $update->getCallbackQuery();
        $chatId = $callbackQuery->getFrom()->getId();
        $data = $callbackQuery->getData(); // مثلا: 'set_model:5'

        // اگر داده مربوط به تنظیم مدل بود
        if (Str::startsWith($data, 'set_model:')) {
            $modelId = Str::after($data, 'set_model:');
            $model = AiModel::find($modelId);

            if ($model) {
                // ذخیره یا آپدیت انتخاب کاربر در دیتابیس
                UserSetting::updateOrCreate(
                    ['chat_id' => $chatId],
                    ['ai_model_id' => $model->id]
                );

                // پاسخ به تلگرام برای بستن لودینگ دکمه
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => "✅ مدل به '{$model->title}' تغییر کرد.",
                ]);

                // ویرایش پیام اصلی برای حذف دکمه‌ها
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $callbackQuery->getMessage()->getMessageId(),
                    'text' => "مدل با موفقیت به *{$model->title}* تغییر یافت. حالا می‌توانید پیام خود را ارسال کنید.",
                    'parse_mode' => 'Markdown'
                ]);

            }
        }
    }

    /**
     * مدیریت پیام‌های متنی عادی و ارسال به API
     */
    private function handleTextMessage($chatId, $text, $messageId)
    {
        // پیدا کردن مدل انتخاب شده توسط کاربر
        $userSetting = UserSetting::where('chat_id', $chatId)->first();
        $modelName = 'deepseek-chat'; // مدل پیش‌فرض اگر کاربر مدلی انتخاب نکرده باشد

        if ($userSetting && $userSetting->aiModel) {
            $modelName = $userSetting->aiModel->name;
        }

        // ارسال اکشن "در حال تایپ"
        Telegram::sendChatAction([
            'chat_id' => $chatId,
            'action' => 'typing',
        ]);

        $gapApiKey = config('services.gap.key');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $gapApiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.gapgpt.app/v1/chat/completions', [
            'model' => $modelName, // << استفاده از مدل انتخاب شده
            'messages' => [
                ['role' => 'user', 'content' => $text], // << حذف پراپمت اضافی ترجمه برای انعطاف بیشتر
            ],
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ]);

        $reply = 'متاسفم، مشکلی در ارتباط با سرویس هوش مصنوعی پیش آمده.';
        if ($response->successful()) {
            $data = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? $reply;
        } elseif ($response->serverError() || $response->clientError()) {
            // لاگ کردن خطا برای دیباگ
            \Log::error('AI API Error', ['response' => $response->body()]);
        }


        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'reply_to_message_id' => $messageId,
            'parse_mode' => 'Markdown',
        ]);
    }
}
