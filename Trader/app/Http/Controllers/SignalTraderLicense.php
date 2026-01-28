<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\TelegramUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;


class SignalTraderLicense extends Controller
{
    //validation
    public function validation(Request $req)
    {
        $license = $req->license;
        $chatId = strval($req->chat_id);
        
        // Check if the license is already used
        if (Subscription::where("license", $license)->exists()) {
            return response()->json([
                "status" => false,
                "msg" => "This license is already used!"
            ]);
        }
    
        // Validate license from external API
        $url = "https://signalvision.ai/wp-json/subkey/v1/validate?key={$license}&token=anytokens";
        $response = Http::get($url);
        // \Log::info($response);
        
        if (!$response->successful()) {
            return response()->json([
                "status" => false,
                "msg" => "Something went wrong. Please try again later."
            ]);
        }
        $data = $response->json();
        // \Log::info($data);
    
        // check valid 
        if (!isset($data['valid']) || !$data['valid']) {
            return [
                "status" => false,
                "msg" => "Your license is invalid!"
            ];
        }
    
        // check authentication 
        // if (!isset($data['product_id']) || $data['product_id'] != 554) {
        //     return [
        //         "status" => false,
        //         "msg" => "Your license is invalid! Code: 202"
        //     ];
        // }
    
        // Determine package type
        $package_type =  isset($data['variation_attributes']['period']) ? $data['variation_attributes']['period'] : 'Monthly';
    
        // Format dates using Carbon
        $startDate = Carbon::parse($data['start_date'])->toDateTimeString();
        $nextDate = Carbon::parse($data['next_date'])->toDateTimeString();
    
        // Create or update Telegram user
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $user->activation_in = $startDate;
        $user->expired_in = $nextDate;
        $user->subscription_type = $package_type;
        $user->save();
    
        // insert data
        $sub = new Subscription();
        $sub->user_id = $chatId;
        $sub->license = $license;
        $sub->name = $data['name'];
        $sub->email = $data['email'];
        $sub->web_user_id = $data['user_id'];
        $sub->product_id = $data['product_id'];
        $sub->start_date = $data['start_date'];
        $sub->next_date = $data['next_date'];
        $sub->package_type = $package_type;
        $sub->save();
    
        return response()->json([
            "expired_in" => $user->expired_in,
            "type" => $package_type,
            "status" => true,
            "msg" => "License activated successfully."
        ]);
    }
    
    // status 
    public function status(Request $req)
    {
        $chatId = strval($req->chat_id);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        
        return response()->json([
            "expired_in" => $user->expired_in,
            "activation_in" => $user->activation_in,
            "period" => $user->subscription_type,
            "user" => $user
        ]);
    }
}
