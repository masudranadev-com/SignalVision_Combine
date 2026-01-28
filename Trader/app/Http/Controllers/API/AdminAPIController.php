<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TelegramUser;

class AdminAPIController extends Controller
{
    // users 
    public function users(Request $request)
    {
        // Pagination params
        $perPage = $request->get('per_page', 20); // default 20
        $page    = $request->get('page', 1);
        $search    = $request->get('search', null);

        // Paginated users query (NOT get())
        // $users = TelegramUser::orderBy("id", "desc")
        //     ->paginate($perPage, ['*'], 'page', $page);

        $users = TelegramUser::orderBy('id', 'desc')
        ->when(!empty($search), function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', "%{$search}%")
                ->orWhere('chat_id', 'LIKE', "%{$search}%");
            });
        })
        ->paginate($perPage, ['*'], 'page', $page);

        // Transform the paginated results
        $formatted = $users->getCollection()->map(function ($user) {
            return [
                "id" => $user->id,
                "user_id" => $user->chat_id,
                "state" => $user->state,
                "subscription_type" => $user->subscription_type,
                "activation_in" => $user->activation_in,
                "expired_in" => $user->expired_in,
                "shot_is_paid" => $user->expired_in !== null && $user->expired_in > now(),
                "shot_expired_in"    => $user->expired_in?->format('d M, y h:i A'),
                "shot_activation_in"=> $user->activation_in?->format('d M, y h:i A'),
                "money_management_status" => $user->money_management_status,
                "money_management_type" => $user->money_management_type,
                "money_management_risk" => $user->money_management_risk,
                "bybit_balance" => $user->money_management_bybit_wallet_balance,
                "binance_balance" => $user->money_management_binance_wallet_balance,
                "money_management_demo_wallet_balance" => $user->money_management_demo_wallet_balance,
                "money_management_demo_available_balance" => $user->money_management_demo_available_balance,
                "money_management_max_exposure" => $user->money_management_max_exposure,
                "money_management_trade_limit" => $user->money_management_trade_limit,
                "money_management_stop_trades" => $user->money_management_stop_trades,
                "money_management_daily_loss" => $user->money_management_daily_loss,
                "money_management_status_max_exposure" => $user->money_management_status_max_exposure,
                "money_management_status_trade_limit" => $user->money_management_status_trade_limit,
                "money_management_status_stop_trades" => $user->money_management_status_stop_trades,
                "money_management_status_daily_loss" => $user->money_management_status_daily_loss,
                "money_management_uni_leverage_status" => $user->money_management_uni_leverage_status,
                "money_management_uni_leverage" => $user->money_management_uni_leverage,
                "money_management_uni_strategy_status" => $user->money_management_uni_strategy_status,
                "money_management_exchange" => $user->money_management_exchange,
                "money_management_trades_mode" => $user->money_management_trades_mode,
                "money_management_profit_strategy" => $user->money_management_profit_strategy,
                "money_management_profit_strategy_tp" => $user->money_management_profit_strategy_tp,
                "money_management_profit_strategy_partial" => $user->money_management_profit_strategy_partial,
                "created_at" => $user->created_at,
                "updated_at" => $user->updated_at,
            ];
        });

        // Return paginated JSON response
        return response()->json([
            "data" => $formatted,
            "current_page" => $users->currentPage(),
            "per_page" => $users->perPage(),
            "total" => $users->total(),
            "last_page" => $users->lastPage(),
        ]);
    }
}
