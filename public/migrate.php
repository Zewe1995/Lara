<?php

// بارگذاری autoload از پوشه vendor
require __DIR__ . '/../vendor/autoload.php';

// بارگذاری برنامه لاراول
$app = require_once __DIR__ . '/../bootstrap/app.php';

// بارگذاری محیط
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// اجرای دستور migrate
use Illuminate\Support\Facades\Artisan;

Artisan::call('migrate', [
    '--force' => true,
]);

// نمایش خروجی مایگریت
echo nl2br(Artisan::output());
