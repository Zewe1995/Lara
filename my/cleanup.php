<?php
// cleanup.php - برای پاکسازی فایل‌های کش قدیمی
// این فایل رو هر روز یک بار اجرا کنید

// پاکسازی کش قدیمی
if (file_exists('message_cache.json')) {
    $content = file_get_contents('message_cache.json');
    if ($content !== false) {
        $cacheData = json_decode($content, true) ?? [];
        $now = time();
        $validItems = [];

        foreach ($cacheData as $hash => $item) {
            // نگه‌داری آیتم‌های کمتر از 5 دقیقه
            if (($now - $item['timestamp']) <= 300) {
                $validItems[$hash] = $item;
            }
        }

        file_put_contents('message_cache.json', json_encode($validItems));
        echo "Cache cleaned. Removed " . (count($cacheData) - count($validItems)) . " old items.\n";
    }
}

// پاکسازی rate limit قدیمی
if (file_exists('rate_limit.json')) {
    $content = file_get_contents('rate_limit.json');
    if ($content !== false) {
        $rateLimitData = json_decode($content, true) ?? [];
        $now = time();
        $validData = [];

        foreach ($rateLimitData as $chatId => $timestamps) {
            $validTimestamps = array_filter($timestamps, function($timestamp) use ($now) {
                return ($now - $timestamp) < 3600; // نگه‌داری 1 ساعت
            });

            if (!empty($validTimestamps)) {
                $validData[$chatId] = array_values($validTimestamps);
            }
        }

        file_put_contents('rate_limit.json', json_encode($validData));
        echo "Rate limit data cleaned.\n";
    }
}

// پاکسازی لاگ‌های قدیمی (بیش از 7 روز)
if (file_exists('bot_errors.log')) {
    $logContent = file_get_contents('bot_errors.log');
    $lines = explode("\n", $logContent);
    $validLines = [];
    $cutoffTime = time() - (7 * 24 * 3600); // 7 روز قبل

    foreach ($lines as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $lineTime = strtotime($matches[1]);
            if ($lineTime > $cutoffTime) {
                $validLines[] = $line;
            }
        } else if (!empty(trim($line))) {
            $validLines[] = $line; // خطوط بدون تاریخ را نگه دار
        }
    }

    file_put_contents('bot_errors.log', implode("\n", $validLines));
    echo "Log file cleaned. Removed " . (count($lines) - count($validLines)) . " old lines.\n";
}

echo "Cleanup completed successfully!\n";
?>
