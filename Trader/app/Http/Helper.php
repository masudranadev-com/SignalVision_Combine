<?php 
use App\Models\Subscription;
use Telegram\Bot\Laravel\Facades\Telegram;
use Carbon\Carbon;
use App\Models\TelegramUser;


function formatNumberFlexible($number, $decimals = 8) {
    $cleanNumber = str_replace(',', '', $number);
    $num = (float)$cleanNumber;
    $factor = pow(10, $decimals);
    $truncated = floor($num * $factor) / $factor;
    $formatted = number_format($truncated, $decimals, '.', '');
    return $formatted;
}

// license 
function licenseValidation($license, $chatId)
{
    // Check if the license is already used
    if (Subscription::where("license", $license)->exists()) {
        return [
            "status" => false,
            "msg" => "This license is already used!"
        ];
    }

    // Validate license from external API
    $url = "https://signalvision.ai/wp-json/subkey/v1/validate?key={$license}&token=anytokens";
    $response = Http::get($url);

    if (!$response->successful()) {
        return [
            "status" => false, 
            "msg" => $response["message"] ?? "Something went wrong. Please try again later."
        ];
    }

    $data = $response->json();

    // check valid 
    if (!isset($data['valid']) || !$data['valid']) {
        return [
            "status" => false,
            "msg" => "Your license is invalid!"
        ];
    }

    // IF SIGNALMANAGER
    if(!isset($data['product_id']) || $data['product_id'] == env("SIGNALMANAGER_PRODUCT_ID")) {
        return [
            "status" => false,
            "msg" => "This license is only for SignalManager. Please go to the SignalManager Bot and try to activate it."
        ];
    }

    // check authentication 
    if (!isset($data['product_id']) || $data['product_id'] != env("SIGNALSHOT_PRODUCT_ID")) {
        return [
            "status" => false,
            "msg" => "Your license is invalid! Code: 202"
        ];
    }

    // Determine package type
    $package_type =  $data["variation_attributes"]['period'] ?? "Trail";

    // Format dates using Carbon
    $startDate = $data['start_date'];
    $nextDate = $data['next_date'];

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

    return [
        "status" => true,
        "msg" => "License activated successfully.",
    ];
}
function licenseCheck($chatId)
{
    return [
        "status" => true,
        "user_type" => 'free'
    ];

    $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
    $userType = "demo";

    if(is_null($user->expired_in)){
        $schedules = ScheduleCrypto::where("chat_id", $chatId)->get();
        if(count($schedules) >= 3){
            return [
                "status" => false,
                "type" => "license_limit",
            ];
        }
    }else{
        $expired = Carbon::parse($user->expired_in);
        $diffInHours = now()->diffInHours($expired, false);
        $userType = "real";

        // check validity  
        if ($diffInHours <= 0) {
            return [
                "status" => false,
                "type" => "license_status"
            ];
        }
        
        // check total signals  
        $schedules = ScheduleCrypto::where("chat_id", $chatId)->where("status", "running")->get();

        if(count($schedules) >= 6){
            return [
                "status" => false,
                "type" => "license_premium_limit"
            ];
        }
    }

    return [
        "status" => true,
        "user_type" => $userType
    ];
}