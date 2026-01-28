<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CryptoPrice;

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

Route::get('crypto', [CryptoPrice::class, 'crypto']);
Route::get('instruments-price', [CryptoPrice::class, 'instrumentsPrice']);
Route::get('instruments-info', [CryptoPrice::class, 'instrumentsInfo']);


Route::get('/', function () {
      echo "SignalShot";
});

Route::any('telegram-message-webhook', [App\Http\Controllers\TelegramBotController::class, 'telegram_webhook']);
