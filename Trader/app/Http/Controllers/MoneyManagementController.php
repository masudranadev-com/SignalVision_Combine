<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;

class MoneyManagementController extends Controller
{
    // info
    public function info(Request $req)
    {
        $chatId = strval($req->chat_id);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);

        $bybit_wallet_balance = $user["money_management_bybit_wallet_balance"];
        $binance_wallet_balance = $user["money_management_binance_wallet_balance"];
        $demo_wallet_balance = $user["money_management_demo_wallet_balance"];
        $wallet_balance = 
        
        $risk_percentage = $user["money_management_risk"];

        $max_exposure = $user["money_management_max_exposure"];
        $trade_limit = $user["money_management_trade_limit"];
        $daily_loss = $user["money_management_daily_loss"];
        $stop_trades = $user["money_management_stop_trades"];
        // ststus 
        $max_exposure_status = $user["money_management_status_max_exposure"];
        $trade_limit_status = $user["money_management_status_trade_limit"];
        $daily_loss_status = $user["money_management_status_daily_loss"];
        $stop_trades_status = $user["money_management_status_stop_trades"];
        $demo_available_balance = $user["money_management_demo_available_balance"];

        $money_management_status = $user["money_management_status"];
        $money_management_type = $user["money_management_type"];
        
        return response()->json([
            "leverage_status" => $user->money_management_uni_leverage_status === "active" ? true : false,
            "leverage" => $user->money_management_uni_leverage,

            // uni strategy
            "strategy_status" => $user->money_management_uni_strategy_status,
            "strategy_exchange" => $user->money_management_exchange,
            "strategy_mode" => $user->money_management_type ?? 'demo',
            "strategy_profit_strategy" => $user->money_management_profit_strategy,
            "strategy_strategy_tp" => $user->money_management_profit_strategy_tp,
            "strategy_strategy_partial" => $user->money_management_profit_strategy_partial,

            // money management
            "money_management_type" => $money_management_type,
            "risk_percentage" => $risk_percentage,

            "max_exposure" => $max_exposure,
            "trade_limit" => $trade_limit,
            "daily_loss" => $daily_loss,
            "stop_trades" => $stop_trades,
            
            // ststus 
            "max_exposure_status" => $max_exposure_status === "active" ? true : false,
            "trade_limit_status" => $trade_limit_status === "active" ? true : false,
            "daily_loss_status" => $daily_loss_status === "active" ? true : false,
            "stop_trades_status" => $stop_trades_status === "active" ? true : false,

            "money_management_status" => $money_management_status === "active" ? true : false,
            "bybit_wallet_balance" => $bybit_wallet_balance,
            "binance_wallet_balance" => $binance_wallet_balance,
            "demo_wallet_balance" => $demo_wallet_balance,
            "demo_available_balance" => $demo_available_balance,
        ]);
    }

    // toggle 
    public function toggle(Request $req)
    {
        $chatId = $req->chat_id;
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $status = $user->money_management_status === "active" ? "inactive" : "active";
        $user->money_management_status = $status;
        $user->save();

        
        return response()->json([
            "status" => true
        ]);
    }
    
    // config 
    public function getConfig($chatId)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        
        return [
            "status" => $user->money_management_status,
            "risk" => $user->money_management_risk,
            "wallet_balance" => $user->money_management_wallet_balance,
            "max_exposure" => $user->money_management_max_exposure,
            "trade_limit" => $user->money_management_trade_limit,
            "stop_trades" => $user->money_management_stop_trades,
            "daily_loss" => $user->money_management_daily_loss,
            "status_max_exposure" => $user->money_management_status_max_exposure,
            "status_trade_limit" => $user->money_management_status_trade_limit,
            "status_stop_trades" => $user->money_management_status_stop_trades,
            "status_daily_loss" => $user->money_management_status_daily_loss,
        ];
    }
    
    // update toogle 
    public function updateConfig(Request $req)
    {
        $chatId = strval($req->chat_id);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $user[$req->attr] = $req->value;
        $user->save();
        
        return response()->json([
            "status" => $user->money_management_status,
            "risk" => $user->money_management_risk,
            "wallet_balance" => $user->money_management_wallet_balance,
            "demo_available_balance" => $user->money_management_demo_available_balance,
            "max_exposure" => $user->money_management_max_exposure,
            "trade_limit" => $user->money_management_trade_limit,
            "stop_trades" => $user->money_management_stop_trades,
            "daily_loss" => $user->money_management_daily_loss,
            "status_max_exposure" => $user->money_management_status_max_exposure,
            "status_trade_limit" => $user->money_management_status_trade_limit,
            "status_stop_trades" => $user->money_management_status_stop_trades,
            "status_daily_loss" => $user->money_management_status_daily_loss,
        ]);
    }

    public function getRisk(Request $req)
    {
        $chatId = strval($req->chat_id);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]); 

        $bybit_wallet_balance = $user["money_management_bybit_wallet_balance"];
        $binance_wallet_balance = $user["money_management_binance_wallet_balance"];
        $demo_wallet_balance = $user["money_management_demo_wallet_balance"];
        $wallet_balance = 
        
        $risk_percentage = $user["money_management_risk"];

        $max_exposure = $user["money_management_max_exposure"];
        $trade_limit = $user["money_management_trade_limit"];
        $daily_loss = $user["money_management_daily_loss"];
        $stop_trades = $user["money_management_stop_trades"];
        // ststus 
        $max_exposure_status = $user["money_management_status_max_exposure"];
        $trade_limit_status = $user["money_management_status_trade_limit"];
        $daily_loss_status = $user["money_management_status_daily_loss"];
        $stop_trades_status = $user["money_management_status_stop_trades"];
        $demo_available_balance = $user["money_management_demo_available_balance"];

        $money_management_status = $user["money_management_status"];
        $money_management_type = $user["money_management_type"];

        return [
            "money_management_type" => $money_management_type,
            "percentage" => $risk_percentage,

            "max_exposure" => $max_exposure,
            "trade_limit" => $trade_limit,
            "daily_loss" => $daily_loss,
            "stop_trades" => $stop_trades,
            // ststus 
            "max_exposure_status" => $max_exposure_status === "active" ? true : false,
            "trade_limit_status" => $trade_limit_status === "active" ? true : false,
            "daily_loss_status" => $daily_loss_status === "active" ? true : false,
            "stop_trades_status" => $stop_trades_status === "active" ? true : false,

            "money_management_status" => $money_management_status === "active" ? true : false,
            "bybit_wallet_balance" => $bybit_wallet_balance,
            "binance_wallet_balance" => $binance_wallet_balance,
            "demo_wallet_balance" => $demo_wallet_balance,
            "demo_available_balance" => $demo_available_balance,
        ];
    }

    // update toogle 
    public function demoBalanceUpdate(Request $req)
    {
        $chatId = strval($req->chat_id);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $user->money_management_demo_available_balance = $user->money_management_demo_available_balance + $req->value;
        $user->save();
        
        return response()->json([
            "demo_available_balance" => $user->money_management_demo_available_balance,
        ]);
    }
}
