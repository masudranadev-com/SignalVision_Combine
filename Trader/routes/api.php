<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CryptoApiBybit;
use App\Http\Controllers\CryptoApiBinance;
use App\Http\Controllers\SignalTraderLicense;
use App\Http\Controllers\MoneyManagementController;
use App\Http\Controllers\API\AdminAPIController;
use App\Http\Controllers\API\SupportAPIController;
use App\Models\TelegramUser;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('crypto.api.secret')->group(function () {
    // Route::prefix('bybit')->controller(CryptoApiBybit::class)->group(function () {
    //     // API STATUS 
    //     Route::post('status', 'status');
    //     Route::post('clear', 'clear');
    //     Route::post('set', 'set');
    //     Route::get('test', 'openTrade');
        
    //     Route::post('wallet-balance', 'walletBalance');
    //     Route::post('open-trade', 'openTrade');
    //     Route::post('market-entry', 'marketEntry');
    //     Route::post('position-status', 'positionStatus');
    //     Route::post('order-status', 'orderStatus');
    //     Route::post('open-partial-trade', 'openPartialTrade');
    //     Route::post('close-trade', 'closeTrade');
    //     Route::post('close-position', 'closePosition');
    //     Route::post('list-position', 'listPosition');

    //     // Update routes
    //     Route::post('update-trade-tp-sl', 'updateTradeTPStop');
    // });

    #BYBIT
    Route::prefix('bybit')->controller(CryptoApiBybit::class)->group(function () {
        Route::post('wallet', 'wallet');
        Route::post('orders/smart', 'smartOrder');
        Route::post('orders/market', 'placeMarket');
        Route::post('orders/partial', 'openPartialOrder');
        Route::post('position/partial-close', 'partialClose');
        Route::post('order/close', 'closeOrder');
        Route::post('update/tp-sl', 'updateTPSL');
        Route::post('positions/tp-sl', 'updatePositionTPSL');
        Route::post('positions-order/lists/{user_id}', 'positionOrderLists');
    });

    #BINANCE
    Route::prefix('binance')->controller(CryptoApiBinance::class)->group(function () {
        // API STATUS 
        Route::get('test', 'test');
        
        Route::post('wallet-balance', 'walletBalance');
        Route::post('open-trade', 'placeOrder');
        Route::post('market-entry', 'marketEntry');
        Route::post('position-status', 'positionStatus');
        Route::post('open-partial-trade', 'openPartialTrade');
        Route::post('close-trade', 'cancelOrder');
        Route::post('close-position', 'closePosition');
        Route::post('list-position', 'listPosition');

        // Update routes
        Route::post('update-trade-tp-sl', 'updateTradeTPStop');
    });
    
    // license 
    Route::prefix('license')->controller(SignalTraderLicense::class)->group(function () {
        Route::post('validation', 'validation');
        Route::post('status', 'status');
    });
    
    // money  management
    Route::prefix('money-management')->controller(MoneyManagementController::class)->group(function () {
        Route::post('info', 'info');

        Route::post('get-risk', 'getRisk');
        Route::post('update-config', 'updateConfig');
        Route::post('demo-balance-update', 'demoBalanceUpdate');
        Route::get('uni-strategy', 'uniStrategy');
    });

    // telegram users
    Route::prefix('telegram-users')->controller(TelegramUserController::class)->group(function () {
        Route::get('/', 'index');
    });

    #ADMIN
    Route::prefix('admin')->controller(AdminAPIController::class)->group(function () {
        Route::post('users', 'users');
    });

    #SUPPORT CONTROLLER
    Route::prefix('support-bot')->controller(SupportAPIController::class)->group(function () {
        Route::post('registration', 'registration');
        Route::post('user-info', 'userInfo');
        Route::post('license-activation', 'licenseActivation');
    });

    // Test Data Clear (For Testing Only)
    // Route::get('clear-test-data', function () {
    //     try {
    //         \DB::table('telegram_users')->truncate();
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Test data cleared successfully! All telegram users have been removed.'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error clearing data: ' . $e->getMessage()
    //         ], 500);
    //     }
    // });
});