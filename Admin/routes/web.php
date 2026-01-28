<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\BotTokenController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/info', function () {
      echo "SignalAdmin";
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/test', function(){
    $trades = Http::withHeaders([
        'API-SECRET' => config('services.api.secret')
    ])->post(config('services.api.manager_end_point') . '/api/admin/trades');

    $managerRes = Http::withHeaders([
        'API-SECRET' => config('services.api.secret')
    ])->post(config('services.api.manager_end_point') . '/api/admin/users')->json();

    $shotRes = Http::withHeaders([
        'API-SECRET' => config('services.api.secret')
    ])->post(config('services.api.shot_end_point') . '/api/admin/users')->json();

    $shotData = collect($shotRes['data'])->keyBy('user_id');

    $merged = collect($managerRes['data'])->map(function ($user) use ($shotData) {
        $shot = $shotData->get($user['user_id'], []);
        return array_merge($user, $shot);
    });

    $response = [
        'data' => $merged->values(),
        'current_page' => $managerRes['current_page'],
        'per_page' => $managerRes['per_page'],
        'total' => $merged->count(),
        'last_page' => $managerRes['last_page'],
    ];

    return response()->json($response);
});

Route::middleware(['admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // users
    Route::controller(UserController::class)->name('user.')->prefix('user')->group(function(){
        Route::get('/all', 'all')->name('all');
        Route::get('/paid', 'paid')->name('paid');
        Route::get('/free', 'free')->name('free');
        Route::post('/send-message', 'sendMessage')->name('send-message');
    });

    // support
    Route::controller(SupportController::class)->name('support.')->prefix('support')->group(function(){
        Route::get('/index', 'index')->name('index');
    });

    // bot tokens
    Route::controller(BotTokenController::class)->name('bot-token.')->prefix('bot-token')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}/edit', 'edit')->name('edit');
        Route::post('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
        Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status');
        Route::get('/stats', 'stats')->name('stats');
    });
});
