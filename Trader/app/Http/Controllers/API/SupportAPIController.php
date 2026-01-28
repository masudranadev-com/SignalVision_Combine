<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SupportAPIController extends Controller
{
    public function registration(Request $req)
    {
        $chatId = strval($req->chat_id);
        $username = $req->username;
        $firstName = $req->first_name;
        $lastName = $req->last_name;

        $user = new TelegramUser();
        $user->chat_id = $chatId;
        $user->username = $username;
        // $user->first_name = $firstName;
        // $user->last_name = $lastName;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'user_id' => $user->id
        ]);
    }

    public function userInfo(Request $req)
    {
        $chatId = strval($req->chat_id);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);

        $signalShotData = [
            "mode" => $user->money_management_type ?? "demo",
            "money_management_status" => $user->money_management_status ?? "inactive",
            "demo_balance" => [
                "value" => $user->money_management_demo_wallet_balance ?? 0,
                "status" => ($user->money_management_type == "demo") ? "active" : "inactive"
            ],
            "risk_per_trade" => [
                "value" => $user->money_management_risk ?? 0,
                "status" => $user->money_management_risk_status ?? "inactive"
            ],
            "uni_leverage" => [
                "value" => $user->money_management_uni_leverage ?? 0,
                "status" => $user->money_management_uni_leverage_status ?? "inactive"
            ],
            "uni_strategy_status" => $user->money_management_uni_strategy_status ?? "inactive",
            "safety_rules" => [
                "max_exposure" => $user->money_management_max_exposure ?? 0,
                "max_trade" => $user->money_management_trade_limit ?? 0,
                "daily_loss_limit" => $user->money_management_daily_loss ?? 0,
            ],
        ];

        return response()->json($signalShotData);
    }


    public function licenseActivation(Request $req)
    {
        $chatId = strval($req->chat_id);
        $data = $req->all();

        $startDate = Carbon::parse($data['activation_in']);
        $endDate = Carbon::parse($data['expired_in']);

        // Create or update Telegram user
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $user->activation_in = $startDate;
        $user->expired_in = $endDate;
        $user->subscription_type = $data['subscription_type'];
        $user->save();

        return [
            "status" => true,
            "msg" => "License activated successfully.",
        ];
    }
}
