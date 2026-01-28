<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
use App\Events\TradeConfigEvent;
use App\Models\ScheduleCrypto;
use App\Jobs\ScheduleNotificationJob;
use \App\Http\Controllers\BybitAPIController;

Route::get('/send-message', function () {
    // $data = [
    //     'id' => 1,
    //     'type' => 'TP1',
    //     'current_price' => '4.98',
    //     'data' => []
    // ];

    // dispatch((new ScheduleNotificationJob($data))->onQueue('SignalManager_notification'));

    $trade = ScheduleCrypto::find(1);
    event(new TradeConfigEvent("add", $trade));
    return;

    $dataAPI = new BybitAPIController();
    $dataAPI->positionStatus($trade);
    return $dataAPI;
    $dataAPI->partialTrade($trade);

    return 'Message sent successfully!';
});
Route::get('/index-send-message', function () {
    return view("index-send-message");
});

//+++++++++++++++++++++++++++++
Route::any('test', [App\Http\Controllers\TelegramBotController::class, 'test']);
Route::any('telegram-message-webhook', [App\Http\Controllers\TelegramBotController::class, 'telegram_webhook']);
Route::get('crypto', [App\Http\Controllers\TelegramBotController::class, 'crypto']);

// SignalShot 
Route::controller(App\Http\Controllers\TelegramBotController::class)->prefix("signal-shot")->group(function(){
    Route::get('new-signal', 'SignalShot_NewSignal');
});

// webapp 
Route::controller(App\Http\Controllers\TelegramBotController::class)->prefix("web-app")->group(function(){
    Route::get('custom-partial', 'customPartial');
});



Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
