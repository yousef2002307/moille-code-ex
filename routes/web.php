<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

// Debug route - check webhook URL
Route::get('/debug/webhook-url', function () {
    $webhookUrl = route('payment.webhook');
    $redirectUrl = route('payment.return');
    $appUrl = config('app.url');
    
    return response()->json([
        'app_url' => $appUrl,
        'webhook_url' => $webhookUrl,
        'redirect_url' => $redirectUrl,
        'webhook_includes_localhost' => str_contains($webhookUrl, 'localhost'),
        'webhook_includes_127' => str_contains($webhookUrl, '127.0.0.1'),
        'webhook_will_be_sent' => !str_contains($webhookUrl, '127.0.0.1') && !str_contains($webhookUrl, 'localhost'),
    ]);
});

// Payment routes
Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
Route::post('/payment', [PaymentController::class, 'store'])->name('payment.store');
Route::get('/payment/return', [PaymentController::class, 'handleReturn'])->name('payment.return');
Route::post('/payment/webhook', [PaymentController::class, 'handleWebhook'])->name('payment.webhook');
