<?php


$telegramToken = '8042806154:AAEfPvKYRfLoHvZ70vIj4PlQ4btqflFRmn4';
$gapApiKey = 'sk-Srl5P534RBwmzNb0SBqCTGTTa1ZCJDWOoFAqcw3ecsSNiA5e';

define('TELEGRAM_API_URL', "https://api.telegram.org/bot$telegramToken/");
define('GAP_API_URL', 'https://api.gapgpt.app/v1/chat/completions');

// دریافت پیام ورودی
$update = json_decode(file_get_contents('php://input'), true);
if (!isset($update['message']['text'], $update['message']['chat']['id'])) exit;

$chatId = $update['message']['chat']['id'];
$messageId = $update['message']['message_id'];
$text = $update['message']['text'];

// ارسال اکشن تایپینگ
file_get_contents(TELEGRAM_API_URL . "sendChatAction?chat_id=$chatId&action=typing");

// ارسال به GAP GPT برای ترجمه به فارسی
$prompt = "لطفاً این متن را به فارسی ترجمه کن:\n\n$text";
$data = [
    'model' => 'deepseek-chat',
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.3,
    'max_tokens' => 1000,
];

$ch = curl_init(GAP_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $gapApiKey",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
curl_close($ch);

$reply = "متاسفم، مشکلی پیش آمده.";
if ($response) {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $reply = $result['choices'][0]['message']['content'];
    }
}

// ارسال پاسخ به کاربر
file_get_contents(TELEGRAM_API_URL . "sendMessage?" . http_build_query([
        'chat_id' => $chatId,
        'text' => $reply,
        'reply_to_message_id' => $messageId,
        'parse_mode' => 'Markdown'
    ]));



?>
