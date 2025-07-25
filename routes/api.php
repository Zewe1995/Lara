<?php



use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;


Route::post('/webhook', [TelegramWebhookController::class, 'handle']);

Route::get('/test-api', function () {
    return response()->json(['message' => 'API is working!']);
});

