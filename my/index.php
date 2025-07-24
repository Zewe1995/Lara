<?php
// ------------------ CONFIGURATION ------------------ //

// توکن ربات تلگرام خود را اینجا قرار دهید
$telegramToken = '8040758083:AAFngueeFNN7xOryzlzIEtqGZl1R6QjUpMA';

// کلید API جمینای خود را که از Google AI Studio دریافت کرده‌اید، اینجا قرار دهید
$geminiApiKey = 'AIzaSyAkFqBskNQWgbQSrYd_V00f7PFmkW3uJ54'; // <--- کلید API جمینای خود را اینجا وارد کنید

// ------------------ API CONSTANTS ------------------ //

// آدرس API تلگرام
define('TELEGRAM_API_URL', "https://api.telegram.org/bot$telegramToken/");

// آدرس API جمینای
define('GEMINI_API_URL', "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=$geminiApiKey");

// حداکثر طول پیام در تلگرام
const MAX_MESSAGE_LENGTH = 4090;

// ------------------ HELPER FUNCTIONS ------------------ //

/**
 * برای لاگ کردن خطاها در فایلی به نام bot_errors.log
 * @param string $message پیام خطا
 */
function logError(string $message): void
{
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'bot_errors.log');
}

/**
 * ارسال درخواست به سرورهای تلگرام با استفاده از cURL
 * @param string $method متد API تلگرام (مانند sendMessage)
 * @param array $payload داده‌هایی که باید ارسال شوند
 */
function sendTelegramRequest(string $method, array $payload): void
{
    $ch = curl_init(TELEGRAM_API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30
    ]);
    // اجرای درخواست (پاسخ نادیده گرفته می‌شود)
    curl_exec($ch);
    curl_close($ch);
}

/**
 * ارسال پیام‌های طولانی با تقسیم آن‌ها به چند بخش
 * @param int|string $chatId شناسه چت مقصد
 * @param string $text متنی که باید ارسال شود
 */
function sendLongMessage($chatId, string $text): void
{
    // اگر طول پیام کمتر از حد مجاز است، آن را مستقیماً ارسال کن
    if (mb_strlen($text, 'UTF-8') <= MAX_MESSAGE_LENGTH) {
        sendTelegramRequest('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown']);
    } else {
        // در غیر این صورت، پیام را به بخش‌های کوچکتر تقسیم کن
        $chunks = mb_str_split($text, MAX_MESSAGE_LENGTH);
        foreach ($chunks as $chunk) {
            sendTelegramRequest('sendMessage', ['chat_id' => $chatId, 'text' => $chunk, 'parse_mode' => 'Markdown']);
            usleep(300000); // تاخیر 0.3 ثانیه‌ای برای جلوگیری از اسپم
        }
    }
}

// ------------------ MAIN LOGIC ------------------ //

// دریافت آپدیت ارسال شده از تلگرام (به صورت webhook)
$update = json_decode(file_get_contents('php://input'), true);

// اگر پیام یا شناسه چت وجود ندارد، اسکریپت را متوقف کن
if (!isset($update['message']['text']) || !isset($update['message']['chat']['id'])) {
    exit();
}

$chatId = $update['message']['chat']['id'];
$userMessage = trim($update['message']['text']);

// اگر پیام کاربر خالی است، خارج شو
if (empty($userMessage)) {
    exit();
}

// ارسال اکشن "در حال نوشتن..." برای بهبود تجربه کاربری
sendTelegramRequest('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);

// آماده‌سازی داده‌ها برای ارسال به Gemini API
// ساختار payload جمینای با مدل‌های دیگر متفاوت است
$geminiPayload = json_encode([
    "contents" => [
        [
            "parts" => [
                ["text" => $userMessage]
            ]
        ]
    ]
]);

// ارسال درخواست به Gemini API با cURL
$ch = curl_init(GEMINI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json" // هدر Authorization لازم نیست چون کلید در URL است
    ],
    CURLOPT_POSTFIELDS => $geminiPayload,
    CURLOPT_TIMEOUT => 90, // افزایش زمان انتظار برای پاسخ‌های طولانی‌تر
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// بررسی نتیجه و پردازش پاسخ
$reply = '';
if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    // استخراج متن پاسخ از ساختار JSON جمینای
    // مسیر صحیح: candidates -> 0 -> content -> parts -> 0 -> text
    $reply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? "❌ پاسخی از Gemini دریافت نشد. لطفاً دوباره تلاش کنید.";
} else {
    // در صورت بروز خطا، آن را لاگ کرده و به کاربر اطلاع بده
    logError("Gemini API Error ($httpCode): $response");
    $reply = "❌ خطایی در ارتباط با سرور Gemini رخ داد (کد: $httpCode). ممکن است مشکل از کلید API یا محدودیت‌های سرویس باشد.";
}

// ارسال پاسخ نهایی به کاربر در تلگرام
sendLongMessage($chatId, $reply);
