<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\CryptoApiBybit;
use App\Http\Controllers\CryptoApiBinance;
use App\Http\Controllers\MoneyManagementController;
use App\Models\Subscription;
use App\Jobs\WebhooksJob;
use Artisan;

class TelegramBotController extends Controller
{
    public $server_ip;
    public function __construct()
    {
        $this->server_ip = env("SERVER_IP");//'54.255.247.52';
    }

    /**
     * Safe wrapper for Telegram API calls with timeout handling
     */
    private function safeTelegramCall($method, $params = [])
    {
        try {
            return Telegram::$method($params);
        } catch (\Telegram\Bot\Exceptions\TelegramSDKException $e) {
            // Log timeout errors silently to avoid flooding logs
            if (str_contains($e->getMessage(), 'cURL error 28') || str_contains($e->getMessage(), 'timeout')) {
                Log::warning('Telegram API timeout', [
                    'method' => $method,
                    'chat_id' => $params['chat_id'] ?? 'unknown',
                    'error' => 'SSL connection timeout'
                ]);
            } else {
                // Log other Telegram errors normally
                Log::error('Telegram API error', [
                    'method' => $method,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected error in Telegram call', [
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function telegram_webhook(Request $request)
    {
        $data = $request->all();
        // $this->telegram_webhook_job($data);
        // return;

        try {
            dispatch((new WebhooksJob($data))->onQueue('SignalTrader'));
            return response()->json(['status' => 'queued'], 200);
        } catch (\Throwable $th) {
            Log::error('Failed to dispatch webhook job', [
                'error' => $th->getMessage()
            ]);
            return response()->json(['status' => 'processed'], 200);
        }
    }
    public function telegram_webhook_job($data)
    {
        try {
            // ✅ Handle button click (callback_query)
            if (isset($data['callback_query'])) {
                $callbackData = $data['callback_query']['data'];
                $chatId = strval($data['callback_query']['message']['chat']['id']);
                $messageId = $data['callback_query']['message']['message_id'];
                $callbackId = $data['callback_query']['id'];
                $user = TelegramUser::where('chat_id', $chatId)->first();

                $mainButtons = [
                    ['🔑 License', '🛠️ API Keys'],
                    ['🆘 Help', '📞 Support'],
                    ['💰 Risk Management', '💎 Upgrade']
                ];

                // home 
                if($callbackData == "main_menu"){
                    $user->state = null;
                    $this->telegramMessageType("main_menu", $chatId);
                }

                /*
                ===========================
                HELP
                ===========================
                */
                else if($callbackData == "help"){
                    $user->state = null;
                    $this->telegramMessageType("help", $chatId);
                }
                else if($callbackData == "help_security"){
                    $user->state = null;
                    $this->telegramMessageType("help_security", $chatId);
                }
                else if($callbackData == "help_ip_setup_guid"){
                    $user->state = null;
                    $this->telegramMessageType("help_ip_setup_guid", $chatId);
                }
                else if($callbackData == "help_troubleshooting"){
                    $user->state = null;
                    $this->telegramMessageType("help_troubleshooting", $chatId);
                }
                else if($callbackData == "help_step_guid"){
                    $user->state = null;
                    $this->telegramMessageType("help_step_guid", $chatId);
                }
                else if($callbackData == "help_api_setup"){
                    $user->state = null;
                    $this->telegramMessageType("help_api_setup", $chatId);
                }

                /*
                ===========================
                LICENSE KEYS 

                ===========================
                */
                else if($callbackData === "license_set"){
                    $user->state = "license_enter";

                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>🔑 Enter your license key:</b>
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'keyboard' => $mainButtons,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true
                        ])
                    ]);
                }
                else if($callbackData === "renew_license"){
                    $user->state = null;

                    // Renew
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>🔑 License Activation</b>

                        Please enter your SignalShot license key to access automated trading features.

                        <b>💎 Not purchased yet?</b>
                        Visit <b>signalvision.ai/shop</b> to get your license.

                        <b>✨ Premium Features:</b>
                        - 🤖 Automated trade execution from SignalManager
                        - 🧪 Demo + Live trading modes
                        - ⚙️ Smart price adjustments for Bybit compliance
                        - 📊 Real-time trade monitoring
                        - 🔄 Seamless integration with your signal sources

                        Enter your license key to continue! 👇
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🌐 Go to Website', 'url' => 'https://signalvision.ai/trader'],
                                ],
                                [
                                    ['text' => '🔙  Back to Menu', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }

                /*
                ====================
                Current Positions
                ====================
                */
                else if($callbackData === "current_position_bybit"){
                    $user->state = null;
                    $this->telegramMessageType("current_position_bybit", $chatId);
                }
                else if($callbackData === "current_position_binance"){
                    $user->state = null;
                    $this->telegramMessageType("current_position_binance", $chatId);
                }

                /*
                ====================
                Bybit
                ====================
                */
                else if($callbackData == "bybit"){
                    $user->state = null;

                    $this->telegramMessageType("bybit", $chatId);
                }
                else if($callbackData == "bybit_api_video_tutorial"){
                    $user->state = null;

                    $this->telegramMessageType("bybit_api_video_tutorial", $chatId);
                }
                else if($callbackData ===  "bybit_api_setup"){
                    $user->state = null;
                    $this->telegramMessageType("bybit_api_setup", $chatId);
                }
                else if($callbackData ===  "bybit_api_credentials"){
                    //KEY
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📝 Enter Bybit API Credentials</b>

                        First, please enter your Bybit API Key:
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    $user->state = 'bybit_api_key';
                }


                /*
                ====================
                Binance
                ====================
                */
                else if($callbackData == "binance"){
                    $user->state = null;

                    $this->telegramMessageType("binance", $chatId);
                }
                else if($callbackData == "binance_api_video_tutorial"){
                    $user->state = null;

                    $this->telegramMessageType("binance_api_video_tutorial", $chatId);
                }
                else if($callbackData ===  "binance_api_setup"){
                    $user->state = null;
                    $this->telegramMessageType("binance_api_setup", $chatId);
                }
                else if($callbackData ===  "binance_api_credentials"){
                    //KEY
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📝 Enter Binance API Credentials</b>

                        First, please enter your Binance API Key:
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    $user->state = 'binance_api_key';
                }
                else if($callbackData === "copy_server_ip"){
                    $serverIp = $this->server_ip;

                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        📋 <b>Copy Our Server IP</b>

                        For enhanced security, add this IP when creating your API keys: <code>{$serverIp}</code>

                        <b>Benefits:</b>
                        • Only our servers can use your keys
                        • Extra security protection
                        • Keys never expire (no 90-day limit)

                        <b>How to add:</b>
                        1. In API settings
                        2. Find "IP Restrictions"
                        3. Add our IP above
                        4. Save changes

                        IP copied to clipboard! 📋
                        EOT,
                        'parse_mode' => 'html',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🔙 Back to Home', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }

                /* 
                ==========================
                💰 Risk Management    
                ==========================
                */
                else if($callbackData === "money_management"){
                    $this->telegramMessageType("money_management", $chatId);
                }
                else if($callbackData === "bybit_method_money_management"){
                    $user->state = "bybit_method";
                    $walletBalance = $user->money_management_bybit_wallet_balance;
                    $this->telegramMessageType("money_management_view", $chatId, ["wallet_balance" => $walletBalance, "type" => "Bybit"]);
                }
                else if($callbackData === "binance_method_money_management"){
                    $user->state = "binance_method";
                    $walletBalance = $user->money_management_binance_wallet_balance;
                    $this->telegramMessageType("money_management_view", $chatId, ["wallet_balance" => $walletBalance, "type" => "Binance"]);
                }

                // Configure Risk  
                else if($callbackData === "configure_risk_money_management"){
                    $this->telegramMessageType("configure_risk_money_management", $chatId);
                }
                else if($callbackData === "custom_percentage_configure_risk_money_management"){
                    $user->state = "custom_percentage_configure_risk_money_management";

                    $this->telegramMessageType("custom_percentage_configure_risk_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'percentage_configure_risk_money_management_')){
                    $percentage = str_replace('percentage_configure_risk_money_management_', '', $callbackData);

                    $user->money_management_risk_status = "active";
                    $user->money_management_risk = $percentage;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("configure_risk_money_management", $chatId);
                    return response('ok');
                }
                else if($callbackData === "disable_enable_risk_money_management"){
                    $status = $user->money_management_risk_status === "active" ? "inactive" : "active";
                    $user->money_management_risk_status = $status;
                    $user->save();
                    
                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("configure_risk_money_management", $chatId);
                    return response('ok');
                }

                // risk analysis 
                else if($callbackData === "risk_analysis_money_management"){
                    $this->telegramMessageType("risk_analysis_money_management", $chatId);
                }

                // Safety Rules 
                else if($callbackData === "safety_rules_money_management"){
                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                }

                else if($callbackData === "max_exposure_safety_rules_money_management"){
                    $this->telegramMessageType("max_exposure_safety_rules_money_management", $chatId);
                }
                else if($callbackData === "custom_percentage_max_exposure_safety_rules_money_management"){
                    $user->state = "custom_percentage_max_exposure_safety_rules_money_management";

                    $this->telegramMessageType("custom_percentage_max_exposure_safety_rules_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'percentage_max_exposure_safety_rules_money_management_')){
                    $percentage = str_replace('percentage_max_exposure_safety_rules_money_management_', '', $callbackData);
                    $user->money_management_status_max_exposure = "active";
                    $user->money_management_max_exposure = $percentage;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }
                else if($callbackData === "disable_enable_max_exposure_safety_rules_money_management"){
                    $user->money_management_status_max_exposure = $user->money_management_status_max_exposure === "active" ? "inactive" : "active";
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }

                else if($callbackData === "trade_limit_safety_rules_money_management"){
                    $this->telegramMessageType("trade_limit_safety_rules_money_management", $chatId);
                }
                else if($callbackData === "custom_limit_trade_limit_safety_rules_money_management"){
                    $user->state = "custom_limit_trade_limit_safety_rules_money_management";

                    $this->telegramMessageType("custom_limit_trade_limit_safety_rules_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'limit_trade_limit_safety_rules_money_management_')){
                    $limit = str_replace('limit_trade_limit_safety_rules_money_management_', '', $callbackData);
                    $user->money_management_status_trade_limit = "active";
                    $user->money_management_trade_limit = $limit;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }
                else if($callbackData === "disable_enable_trade_limit_safety_rules_money_management"){
                    $user->money_management_status_trade_limit = $user->money_management_status_trade_limit === "active" ? "inactive" : "active";
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }

                else if($callbackData === "stop_trades_safety_rules_money_management"){
                    $this->telegramMessageType("stop_trades_safety_rules_money_management", $chatId);
                }
                else if($callbackData === "custom_percentage_stop_trades_safety_rules_money_management"){
                    $user->state = "custom_percentage_stop_trades_safety_rules_money_management";

                    $this->telegramMessageType("custom_percentage_stop_trades_safety_rules_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'percentage_stop_trades_safety_rules_money_management_')){
                    $percentage = str_replace('percentage_stop_trades_safety_rules_money_management_', '', $callbackData);
                    $user->money_management_stop_trades = $percentage;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }
                else if($callbackData === "disable_enable_stop_trades_safety_rules_money_management"){
                    $user->money_management_status_stop_trades = $user->money_management_status_stop_trades === "active" ? "inactive" : "active";
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }

                else if($callbackData === "daily_loss_safety_rules_money_management"){
                    $this->telegramMessageType("daily_loss_safety_rules_money_management", $chatId);
                }
                else if($callbackData === "custom_percentage_daily_loss_safety_rules_money_management"){
                    $user->state = "custom_percentage_daily_loss_safety_rules_money_management";

                    $this->telegramMessageType("custom_percentage_daily_loss_safety_rules_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'percentage_daily_loss_safety_rules_money_management_')){
                    $percentage = str_replace('percentage_daily_loss_safety_rules_money_management_', '', $callbackData);
                    $user->money_management_status_daily_loss = "active";
                    $user->money_management_daily_loss = $percentage;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }
                else if($callbackData === "disable_enable_daily_loss_safety_rules_money_management"){
                    $user->money_management_status_daily_loss = $user->money_management_status_daily_loss === "active" ? "inactive" : "active";
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }

                // enable and disable all
                else if($callbackData === "enable_all_safety_rules_money_management"){
                    $this->telegramMessageType("enable_all_safety_rules_money_management", $chatId);
                }
                else if($callbackData === "disable_all_safety_rules_money_management"){
                    $this->telegramMessageType("disable_all_safety_rules_money_management", $chatId);
                }
                else if($callbackData === "activated_enable_all_safety_rules_money_management"){
                    $user->money_management_status_max_exposure = "active";
                    $user->money_management_status_trade_limit = "active";
                    $user->money_management_status_stop_trades = "active";
                    $user->money_management_status_daily_loss = "active";
                    $user->save();
                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }
                else if($callbackData === "deactivated_disable_all_safety_rules_money_management"){
                    $user->money_management_status_max_exposure = "inactive";
                    $user->money_management_status_trade_limit = "inactive";
                    $user->money_management_status_stop_trades = "inactive";
                    $user->money_management_status_daily_loss = "inactive";
                    $user->save();
                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                    return;
                }

                // help  
                else if($callbackData === "help_money_management"){
                    $this->telegramMessageType("help_money_management", $chatId);
                }

                // status toggle 
                else if($callbackData === "toggle_money_management"){
                    $status = $user->money_management_status === "active" ? "inactive" : "active";
                    $user->money_management_status = $status;
                    $user->save();
                    
                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("money_management", $chatId);
                    return response('ok');
                }

                // type toggle  
                else if($callbackData === "toggle_type_money_management"){
                    $status = $user->money_management_type === "real" ? "demo" : "real";
                    $user->money_management_trades_mode = $status;
                    $user->money_management_type = $status;
                    $user->save();

                    // contecting...
                    $connectingMsg = $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => 'Connecting...',
                    ]);

                    // real  
                    // if($user->money_management_type === "real"){
                    //     $user = TelegramUser::find($user->id);

                    //     #Bybit
                    //     $bybitAPI = new CryptoApiBybit();
                    //     $responseBybit = $bybitAPI->wallet($user->chat_id);

                    //     // check status 
                    //     if($responseBybit['status']){
                    //         $margin_balance = number_format($responseBybit['margin'], 2, '.', '') ?? 0;
                    //         $pl = number_format(($user->money_management_wallet_balance - $margin_balance), 2, '.', '') ?? 0;
                    //         $plText = $pl > 0 ? "+$pl" : $pl;
                    //         $risk_percentage = $user["money_management_risk"];
                    //         $risk = number_format(($user["money_management_risk"]/100)*$margin_balance, 2, '.', '') ?? 0;

                    //         // send msg  
                    //         $this->safeTelegramCall('sendMessage', [
                    //             'chat_id' => $user->chat_id,
                    //             'text' => <<<EOT
                    //             <b>💰 Bybit Money Management - Daily Reset System</b>

                    //             <b>Base Balance (Reset Daily): </b>$margin_balance USDT 🔒
                    //             <b>Last Reset:</b> Today at 00:00 UTC
                    //             <b>Next Reset:</b> Tomorrow at 00:00 UTC

                    //             <b>Current Wallet:</b> {$margin_balance} USDT ({$plText} today)
                    //             <b>Risk per Trade:</b> {$risk_percentage}% of {$margin_balance} = {$risk} USDT

                    //             All trades today use the {$margin_balance} base ✓
                    //             EOT,
                    //             'parse_mode' => 'HTML',
                    //         ]);

                    //         $user->money_management_bybit_wallet_balance = $margin_balance;
                    //         $user->save();
                    //     }

                    //     #binance
                    //     $binanceAPI = app(CryptoApiBinance::class);
                    //     $responseBinance = $binanceAPI->getBalance($chatId);

                    //     // check status 
                    //     if($responseBinance['status']){
                    //         $margin_balance = number_format($responseBinance['available_balance'], 2, '.', '') ?? 0;
                    //         $pl = number_format(($user->money_management_wallet_balance - $margin_balance), 2, '.', '') ?? 0;
                    //         $plText = $pl > 0 ? "+$pl" : $pl;
                    //         $risk_percentage = $user["money_management_risk"];
                    //         $risk = number_format(($user["money_management_risk"]/100)*$margin_balance, 2, '.', '') ?? 0;

                    //         // send msg  
                    //         $this->safeTelegramCall('sendMessage', [
                    //             'chat_id' => $user->chat_id,
                    //             'text' => <<<EOT
                    //             <b>💰 Binance Money Management - Daily Reset System</b>

                    //             <b>Base Balance (Reset Daily): </b>$margin_balance USDT 🔒
                    //             <b>Last Reset:</b> Today at 00:00 UTC
                    //             <b>Next Reset:</b> Tomorrow at 00:00 UTC

                    //             <b>Current Wallet:</b> {$margin_balance} USDT ({$plText} today)
                    //             <b>Risk per Trade:</b> {$risk_percentage}% of {$margin_balance} = {$risk} USDT

                    //             All trades today use the {$margin_balance} base ✓
                    //             EOT,
                    //             'parse_mode' => 'HTML',
                    //         ]);

                    //         $user->money_management_binance_wallet_balance = $margin_balance;
                    //         $user->save();
                    //     }

                    // }
                    
                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $connectingMsg->getMessageId()
                    ]);

                    // reset SignalManager 
                    $response = Http::withHeaders([
                        'API-SECRET' => env("API_SECRET")
                    ])->post(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/risk-management-reset', [
                        "user_id" => $chatId
                    ]);

                    $this->telegramMessageType("money_management", $chatId);
                    return response('ok');
                }
                else if($callbackData === "demo_balance_money_management"){
                    $user->state = "demo_balance_money_management";

                    $this->telegramMessageType("demo_balance_money_management", $chatId);
                }
                else if($callbackData === "refresh_wallet_money_management"){
                    // contecting...
                    $connectingMsg = $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => 'Connecting...',
                    ]);

                    // real  
                    $user = TelegramUser::find($user->id);

                    $response = Http::withHeaders([
                        'API-SECRET' => env("API_SECRET")
                    ])->post(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/get-crypto-wallet', [
                        "user_id" => $chatId,
                    ]);

                    #Bybit
                    // check status 
                    if(isset($response["bybit"]) && !empty($response["bybit"]["available"])){
                        $available_balance = number_format($response["bybit"]['available'], 2, '.', '') ?? 0;
                        $pl = number_format(($user->money_management_wallet_balance - $available_balance), 2, '.', '') ?? 0;
                        $plText = $pl > 0 ? "+$pl" : $pl;
                        $risk_percentage = $user["money_management_risk"];
                        $risk = number_format(($user["money_management_risk"]/100)*$available_balance, 2, '.', '') ?? 0;

                        // send msg  
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $user->chat_id,
                            'text' => <<<EOT
                            <b>💰 Bybit Money Management - Daily Reset System</b>

                            <b>Base Balance (Reset Daily): </b>$available_balance USDT 🔒
                            <b>Last Reset:</b> Today at 00:00 UTC
                            <b>Next Reset:</b> Tomorrow at 00:00 UTC

                            <b>Current Wallet:</b> {$available_balance} USDT ({$plText} today)
                            <b>Risk per Trade:</b> {$risk_percentage}% of {$available_balance} = {$risk} USDT

                            All trades today use the {$available_balance} base ✓
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        $user->money_management_bybit_wallet_balance = $available_balance;
                        $user->save();
                    }

                    #binance
                    // check status 
                    if(isset($response["binance"]) && !empty($response["binance"]["available"])){
                        $available_balance = number_format($response["binance"]['available'], 2, '.', '') ?? 0;
                        $pl = number_format(($user->money_management_wallet_balance - $available_balance), 2, '.', '') ?? 0;
                        $plText = $pl > 0 ? "+$pl" : $pl;
                        $risk_percentage = $user["money_management_risk"];
                        $risk = number_format(($user["money_management_risk"]/100)*$available_balance, 2, '.', '') ?? 0;

                        // send msg  
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $user->chat_id,
                            'text' => <<<EOT
                            <b>💰 Binance Money Management - Daily Reset System</b>

                            <b>Base Balance (Reset Daily): </b>$available_balance USDT 🔒
                            <b>Last Reset:</b> Today at 00:00 UTC
                            <b>Next Reset:</b> Tomorrow at 00:00 UTC

                            <b>Current Wallet:</b> {$available_balance} USDT ({$plText} today)
                            <b>Risk per Trade:</b> {$risk_percentage}% of {$available_balance} = {$risk} USDT

                            All trades today use the {$available_balance} base ✓
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        $user->money_management_binance_wallet_balance = $available_balance;
                        $user->save();
                    }
                    
                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $connectingMsg->getMessageId()
                    ]);

                    $this->telegramMessageType("money_management", $chatId);
                    return response('ok');
                }
                

                // Uni Leverage 
                else if($callbackData === "uni_leverage_money_management"){
                    $this->telegramMessageType("uni_leverage_money_management", $chatId);
                }
                else if($callbackData === "custom_percentage_uni_leverage_money_management"){
                    $user->state = "custom_percentage_uni_leverage_money_management";

                    $this->telegramMessageType("custom_percentage_uni_leverage_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'percentage_uni_leverage_money_management_')){
                    $percentage = str_replace('percentage_uni_leverage_money_management_', '', $callbackData);
                    $user->money_management_uni_leverage_status = "active";
                    $user->money_management_uni_leverage = $percentage;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("uni_leverage_money_management", $chatId);
                    return response('ok');
                }
                else if($callbackData === "toggle_uni_leverage_money_management"){
                    $status = $user->money_management_uni_leverage_status === "active" ? "inactive" : "active";
                    $user->money_management_uni_leverage_status = $status;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("money_management", $chatId);
                    return response('ok');
                }

                // Uni Strategy 
                else if($callbackData === "uni_strategy_money_management"){
                    $this->telegramMessageType("uni_strategy_money_management", $chatId);
                }
                else if($callbackData === "mod_uni_strategy_money_management"){
                    $this->telegramMessageType("mod_uni_strategy_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'mod_uni_strategy_money_management_')){
                    $mod = str_replace('mod_uni_strategy_money_management_', '', $callbackData);

                    // check  
                    if($mod !== "inactive"){
                        if(empty($user->money_management_exchange) || empty($user->money_management_profit_strategy) || empty($user->money_management_status)){
                            $this->safeTelegramCall('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => <<<EOT
                                <b>⚠️ Auto Feature Unavailable</b>

                                To enable the auto feature, you must first:

                                1. Configure your risk management settings
                                2. Activate your risk management

                                Please complete these steps before using the auto feature.
                                EOT,
                                'parse_mode' => 'HTML',
                                'reply_markup' => json_encode([
                                    'inline_keyboard' => [
                                        [
                                            ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management']
                                        ]
                                    ]
                                ])
                            ]);
                            return;
                        }
                    }

                    $user->money_management_uni_strategy_status = $mod;
                    $user->save();

                    // Delete
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $this->telegramMessageType("mod_uni_strategy_money_management", $chatId);
                }
                /* 
                ==========================
                CONFIG 
                ==========================
                */
                else if($callbackData === "config_uni_strategy_money_management"){
                    $this->telegramMessageType("config_uni_strategy_money_management", $chatId);
                }

                // exchange 
                else if(str_starts_with($callbackData, 'exchange_money_management_')){
                    $exchange = str_replace('exchange_money_management_', '', $callbackData);

                    $user->money_management_exchange = $exchange;
                    $user->save();

                    $this->telegramMessageType("trades_mod_money_management", $chatId);
                }

                // mode 
                else if($callbackData === "trades_mod_money_management"){
                    $this->telegramMessageType("trades_mod_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'trades_mod_money_management_select_')){
                    $mode = str_replace('trades_mod_money_management_select_', '', $callbackData);

                    $user->money_management_trades_mode = $mode;
                    $user->save();

                    $this->telegramMessageType("profit_stretegy_money_management", $chatId);
                }

                // profit stretegy 
                else if($callbackData === "profit_stretegy_money_management"){
                    $this->telegramMessageType("profit_stretegy_money_management", $chatId);
                }

                // manual  
                else if($callbackData === 'manual_profit_stretegy_money_management'){
                    $user->money_management_profit_strategy = "manual_management";
                    $user->save();

                    $this->telegramMessageType("confirm_stretegy_money_management", $chatId);
                }

                // close tp 
                else if($callbackData === 'close_tp_profit_stretegy_money_management'){
                    $user->money_management_profit_strategy = "specific_tp";
                    $user->save();

                    $this->telegramMessageType("close_tp_profit_stretegy_money_management", $chatId);
                }
                else if(str_starts_with($callbackData, 'close_tp_profit_stretegy_money_management_type_')){
                    $tp = str_replace('close_tp_profit_stretegy_money_management_type_', '', $callbackData);

                    $user->money_management_profit_strategy_tp = $tp;
                    $user->save();

                    $this->telegramMessageType("confirm_stretegy_money_management", $chatId);
                }

                // partial stretegy 
                else if($callbackData === "partial_profit_stretegy_money_management"){
                    $this->telegramMessageType("partial_profit_stretegy_money_management", $chatId);
                }
                else if($callbackData === "partial_profit_stretegy_money_management_templates"){
                    $this->telegramMessageType("partial_profit_stretegy_money_management_templates", $chatId);
                }
                else if (str_starts_with($callbackData, 'pp:')) {
                    $partials = str_replace('pp:', '', $callbackData);
                    $array = explode(",", $partials);
                    $user->money_management_profit_strategy_partial = [
                        "tp1" => $array[0] ?? null,
                        "tp2" => $array[1] ?? null,
                        "tp3" => $array[2] ?? null,
                        "tp4" => $array[3] ?? null,
                        "tp5" => $array[4] ?? null,
                    ];
                    $user->money_management_profit_strategy = "partial_profits";
                    $user->save();

                    $this->telegramMessageType("confirm_stretegy_money_management", $chatId);
                }

                $user->save();
                return response('ok');
            }

            // check id exits ? 
            if(!isset($data['message'])) return;

            // input text  
            $chatId = strval($data['message']['chat']['id']);
            $text = trim($data['message']['text'] ?? '');
            $messageId = $data['message']['message_id'];
            $user = TelegramUser::where('chat_id', $chatId)->first();
            if(empty($user)){
                $user = new TelegramUser();
                $user->chat_id = $chatId;
                $user->username = isset($data['message']['chat']['username']) ? strval($data['message']['chat']['username']) : '';
                $user->save();
            }

            // start
            if ($text === '/start' || $text === '🏠 Main Menu') {
                $user->state = null;
                $user->save();

                $this->telegramMessageType("main_menu", $chatId);
            }

            else if ($text === '/help' || $text === '🆘 Help') {
                $user->state = null;
                $user->save();
                $this->telegramMessageType("help", $chatId);
            }

            // Support
            else if ($text === '/support' || $text === '📞 Support') {
                $user->state = null;
                $user->save();

                $this->telegramMessageType("support", $chatId);
            }

            // license 
            else if($text === "🔑 License"){
                $user->state = null;
                $user->save();

                $this->telegramMessageType("license", $chatId);
            }

            // current position 
            else if($text === "📊 Current Positions"){
                $user->state = null;
                $user->save();

                $this->telegramMessageType("current_position_method", $chatId);
            }

            // money management 
            else if($text === "💰 Risk Management"){
                $user->state = null;
                $user->save();

                $this->telegramMessageType("money_management", $chatId);
            }

            else if($text === "💎 Upgrade"){
                $user->state = null;
                $user->save();

                $this->telegramMessageType("upgrade", $chatId);
            }

            // current position 
            else if($text === "📊 Current Positions"){
                $user->state = null;
                $user->save();

                $this->telegramMessageType("current_position_method", $chatId);
            }

            /*
            ====================
            API Keys
            ====================
            */ 
            else if($text === "🛠️ API Keys"){
                $user->state = null;
                $user->save();

                $this->telegramMessageType("select_api_keys_method", $chatId);
            }
            
            // Others 
            else{
                /*
                =========================
                license
                =========================
                */
                if($user->state === "license_enter"){
                    // contecting...
                    $connectingMsg = $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => 'Connecting...',
                    ]);

                    // checking 
                    $response = licenseValidation(trim($text), $chatId);

                    // response error 
                    $errMsg = <<<EOT
                    <b>❌ Invalid License Key</b>

                    {$response["msg"]}

                    <b>Please check:</b>
                    • Copy the complete key without spaces
                    • Verify it hasn't expired
                    • Make sure it's not already in use

                    💎 Need help? Visit <b>signalvision.ai/shop</b>

                    Try entering your license key again:
                    EOT;

                    // delete license 
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    // Delete "Connecting..." 
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $connectingMsg->getMessageId(),
                    ]);

                    if (!$response["status"]) {
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => $errMsg,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '🌐 Get License', 'url' => 'https://signalvision.ai/trader'],
                                    ]
                                ]
                            ])
                        ]);

                        return;
                    }

                    // Respond based on license validation result
                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType('license_activated', $chatId);
                }

                /*
                =========================
                Bybit APIS
                =========================
                */
                else if($user->state === "bybit_api_key"){
                    // Check license
                    $license = licenseCheck($chatId);
                    if (!$license["status"]) {
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            ❌ License check failed. Please contact support.
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);
                        return;
                    }

                    $api_key = trim(strval($text));
                    Cache::put("bybit_key_$chatId", $api_key);

                    //Secret
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📝 Now, please enter your Bybit API Secret:</b>

                        ⚠️ Your API Secret is sensitive information. It will be encrypted and stored securely.
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    // delete api key 
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $user->state = 'bybit_api_secret';
                    $user->save();
                }
                else if ($user->state === 'bybit_api_secret') {
                    try {
                        // contecting...
                        $connectingMsg = $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => 'Connecting...',
                        ]);

                        $apiKey = Cache::get("bybit_key_$chatId");
                        $apiSecret = trim((string) $text);

                        $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
                        $apiSecretHeader = env('API_SECRET');

                        $response = Http::withHeaders([
                            'API-SECRET' => $apiSecretHeader,
                        ])->post("{$endpoint}/api/signal-shot/bybit-api-keys", [
                            'user_id'    => $chatId,
                            'api_key'    => $apiKey,
                            'api_secret' => $apiSecret,
                        ]);

                        if (!$response->successful()) {
                            $this->safeTelegramCall('sendMessage', [
                                'chat_id'    => $chatId,
                                'text'       => '<b>Something went wrong. Please try again later.</b>',
                                'parse_mode' => 'HTML',
                            ]);
                        }

                        // Delete API secret message
                        $this->safeTelegramCall('deleteMessage', [
                            'chat_id'    => $chatId,
                            'message_id' => $messageId,
                        ]);

                        Cache::forget("bybit_key_$chatId");

                        // Delete "Connecting..." 
                        $this->safeTelegramCall('deleteMessage', [
                            'chat_id' => $chatId,
                            'message_id' => $connectingMsg->getMessageId(),
                        ]);

                        $this->telegramMessageType('bybit', $chatId);

                    } catch (\Throwable $th) {
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id'    => $chatId,
                            'text'       => '<b>Something went wrong. Please try again later2.</b>',
                            'parse_mode' => 'HTML',
                        ]);
                    }
                }


                /*
                =========================
                Bybit APIS
                =========================
                */
                else if($user->state === "binance_api_key"){
                    // Check license
                    $license = licenseCheck($chatId);
                    if (!$license["status"]) {
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            ❌ License check failed. Please contact support.
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);
                        return;
                    }
                    
                    $api_key = trim(strval($text));
                    Cache::put("binance_key_$chatId", $api_key);

                    //Secret
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📝 Now, please enter your binance API Secret:</b>

                        ⚠️ Your API Secret is sensitive information. It will be encrypted and stored securely.
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    // delete api key 
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    $user->state = 'binance_api_secret';
                    $user->save();
                }
                else if($user->state === "binance_api_secret"){
                    try {
                        // contecting...
                        $connectingMsg = $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => 'Connecting...',
                        ]);

                        $apiKey = Cache::get("binance_key_$chatId");
                        $apiSecret = trim((string) $text);

                        $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
                        $apiSecretHeader = env('API_SECRET');

                        $response = Http::withHeaders([
                            'API-SECRET' => $apiSecretHeader,
                        ])->get("{$endpoint}/api/signal-shot/binance-api-keys", [
                            'user_id'    => $chatId,
                            'api_key'    => $apiKey,
                            'api_secret' => $apiSecret,
                        ]);

                        if (!$response->successful()) {
                            $this->safeTelegramCall('sendMessage', [
                                'chat_id'    => $chatId,
                                'text'       => '<b>Something went wrong. Please try again later.</b>',
                                'parse_mode' => 'HTML',
                            ]);
                        }

                        // Delete API secret message
                        $this->safeTelegramCall('deleteMessage', [
                            'chat_id'    => $chatId,
                            'message_id' => $messageId,
                        ]);

                        Cache::forget("binance_key_$chatId");

                        // Delete "Connecting..." 
                        $this->safeTelegramCall('deleteMessage', [
                            'chat_id' => $chatId,
                            'message_id' => $connectingMsg->getMessageId(),
                        ]);

                        $this->telegramMessageType('binance', $chatId);

                    } catch (\Throwable $th) {
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id'    => $chatId,
                            'text'       => '<b>Something went wrong. Please try again later2.</b>',
                            'parse_mode' => 'HTML',
                        ]);
                    }
                }

                /* 
                ==========================
                💰 Risk Management   
                ==========================
                */

                // config  
                else if($user->state === "custom_percentage_configure_risk_money_management"){
                    $user->money_management_risk = $text;

                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType("confirm_configure_risk_money_management", $chatId);
                }

                // Safety Rules Configuration 
                else if($user->state === "custom_percentage_max_exposure_safety_rules_money_management"){
                    $user->money_management_max_exposure = $text;

                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                }
                else if($user->state === "custom_percentage_daily_loss_safety_rules_money_management"){
                    $user->money_management_daily_loss = $text;

                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                }
                else if($user->state === "custom_percentage_stop_trades_safety_rules_money_management"){
                    $user->money_management_stop_trades = $text;

                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType("safety_rules_money_management", $chatId);
                }

                // demo balance
                else if($user->state === "demo_balance_money_management"){
                    $user->money_management_demo_wallet_balance = $text;
                    $user->money_management_demo_available_balance = $text;

                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType("money_management", $chatId);
                }

                // uni lev  
                else if($user->state === "custom_percentage_uni_leverage_money_management"){
                    $user->money_management_uni_leverage = $text;

                    $user->state = null;
                    $user->save();

                    $this->telegramMessageType("confirm_uni_leverage_money_management", $chatId);
                }
                
            }
        } catch (\Throwable $th) {
            Log::info("Error: $th");
        }
    }

    private function telegramMessageType($type, $chatId, $data=[])
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $mainButtons = [
            ['🔑 License', '🛠️ API Keys'],
            ['🆘 Help', '📞 Support'],
            ['💰 Risk Management', '💎 Upgrade'],
        ];

        // main menu
        if($type == "main_menu"){
            if(empty($user->expired_in)){
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🚀 Welcome to SignalShot!</b>

                    I'm your automated trading assistant that executes trades on Bybit based on the signals you track in SignalManager.

                    <b>Quick Setup:</b>
                    1️⃣ 🔑 Activate license key
                    2️⃣ 🔗 Connect Bybit APIs
                    3️⃣ 📊 Start trading via SignalManager

                    <b>Features:</b>
                    🚀 Live trading execution
                    🧪 Test with SignalManager demo
                    ⚙️ Smart price adjustments
                    📊 Real-time monitoring

                    <b>Safe Testing:</b>
                    Use SignalManager's demo mode with real market prices before trading live!

                    Ready to start? 🎯
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'keyboard' => $mainButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ])
                ]);
            }else{
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>SignalShot License (Track + Execute):</b>

                    ⚠️ 🚀 Welcome to SignalManager!
                    I'm your personal trading assistant that will track your signals, alert you when price targets are reached, and execute trades automatically on your exchange.
                    ⭐ Your SignalShot license is ACTIVE — unlimited tracking + auto-execution enabled!

                    <b>To get started:</b>
                    1️⃣ Connect your exchange in /settings
                    2️⃣ Forward a trading signal from your favorite signal providers
                    3️⃣ I'll set up alerts AND execute your trades automatically
                    
                    Manage your settings and subscription at https://signalvision.ai
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'keyboard' => $mainButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ])
                ]);
            }
        }

        // upgrade
        else if($type == "upgrade"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Upgrade Your Account</b>

                To upgrade your license or manage your subscription, use our Payment Bot.

                <b>From there you can:</b>
                - Purchase SignalManager or SignalShot license
                - Manage your active subscription
                - Access your affiliate dashboard

                Earn 20% commission by referring new users through your personal affiliate link.

                Tap below to open the Payment Bot.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Open Payment Bot', 'url' => 'https://t.me/signalvisionpaybot'],
                        ],
                        [
                            ['text' => 'Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }

        // help 
        else if($type == "help"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                ❓ <b>SignalShot Help Center</b>

                Get help with setup, trading, and troubleshooting:

                <b>📚 Topics:</b>
                • 🔧 API Setup & Configuration
                • 🛡️ Security Best Practices
                • ⚠️ Troubleshooting

                <b>🆘 Need direct help?</b>
                • Contact our support team
                • Join our community chat

                Choose a topic to learn more:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔧 API Setup Help', 'callback_data' => 'help_api_setup'],
                            ['text' => '🛡️ Security Guide', 'callback_data' => 'help_security'],
                        ],
                        [
                            ['text' => '⚠️ Troubleshooting', 'callback_data' => 'help_troubleshooting'],
                            ['text' => '💬 Contact Support', 'callback_data' => 'support'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "help_api_setup"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🔧 <b>API Setup Help</b>

                Choose what you need help with:

                <b>📚 Quick Guides:</b>
                • Step-by-step API creation
                • Security & permissions  
                • Troubleshooting issues

                <b>🎥 Video Tutorials:</b>
                • Bybit API setup walkthrough
                • Security guide
                • Common problems solved

                Select a topic:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Step-by-Step Guide', 'callback_data' => 'help_step_guid'],
                            ['text' => '🛡️ Security Tips', 'callback_data' => 'help_security_tips'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu'],
                        ]
                    ]
                ])
            ]);
        } 
        else if($type == "help_security"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🛡️ <b>Security Essentials</b>

                <b>✅ ONLY Enable These:</b>
                • Orders (place/cancel trades)
                • Positions (manage positions)
                • Trade (Derivatives & Spot)

                <b>❌ NEVER Enable These:</b>
                • Withdrawal (can steal funds!)
                • Account Transfer
                • Subaccount Transfer

                <b>🔒 IP Restriction (Recommended):</b>
                Add our server IP for extra security:
                <code>{$this->server_ip}</code>

                <b>Benefits:</b>
                • Keys never expire (no 90-day limit)
                • Extra security layer
                • Prevents unauthorized access

                <b>🛡️ Other Security:</b>
                • Enable 2FA on Bybit
                • Keep API keys private

                Need help adding IP restriction?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 IP Setup Guide', 'callback_data' => 'ip_setup_guid'],
                            ['text' => '📋 Copy Our IP', 'callback_data' => 'copy_server_ip'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        } 
        else if($type == "help_ip_setup_guid"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🛡️ <b>Security Essentials</b>

                <b>✅ ONLY Enable These:</b>
                • Orders (place/cancel trades)
                • Positions (manage positions)
                • Trade (Derivatives & Spot)

                <b>❌ NEVER Enable These:</b>
                • Withdrawal (can steal funds!)
                • Account Transfer
                • Subaccount Transfer

                <b>🔒 IP Restriction (Recommended):</b>
                Add our server IP for extra security:
                <code>{$this->server_ip}</code>

                <b>Benefits:</b>
                • Keys never expire (no 90-day limit)
                • Extra security layer
                • Prevents unauthorized access

                <b>🛡️ Other Security:</b>
                • Enable 2FA on Bybit
                • Keep API keys private

                Need help adding IP restriction?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 IP Setup Guide', 'callback_data' => 'ip_setup_guid'],
                            ['text' => '📋 Copy Our IP', 'callback_data' => 'copy_server_ip'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "help_troubleshooting"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                ⚠️ <b>Common Issues & Solutions</b>

                <b>🔑 License Issues:</b>
                • "Invalid license" → Check spelling/spaces
                • "Expired license" → Renew at <a href="https://signalvision.ai/shop">signalvision.ai/shop</a>

                <b>🔧 API Connection Issues:</b>
                • "Invalid API keys" → Verify keys are correct
                • "Permission denied" → Enable Orders, Positions, Trade
                • "Keys expired" → Add IP restriction for permanent keys

                <b>📊 Trading Issues:</b>
                • "Order rejected" → Check account balance
                • "Price limit exceeded" → Auto-adjusted by system
                • "No signal execution" → Verify SignalManager connection

                <b>🧪 Demo Testing:</b>
                • Use SignalManager demo mode for safe testing
                • Real market prices without risk

                <b>🚀 Live Trading:</b>
                • Insufficient balance → Deposit funds to Bybit
                • Orders not executing → Check API permissions

                Still need help?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💬 Contact Support', 'callback_data' => 'support'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "help_step_guid"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                📋 <b>How to Add IP Restriction</b>

                <b>Steps:</b>
                1. Go to Bybit → API Management
                2. Click <b>Edit</b> on your API key
                3. Find <b>IP Restrictions</b> section
                4. Click <b>Restrict access to trusted IPs</b>
                5. Add our IP: <code>{$this->server_ip}</code>
                6. Save changes

                <b>✅ Benefits:</b>
                • Permanent keys (no 90-day expiry)
                • Only our servers can use your keys
                • Extra security protection

                <b>⚠️ Important:</b>
                Without IP restriction, keys expire every 90 days!

                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Copy Our IP', 'callback_data' => 'copy_server_ip'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "help_security_tips"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🛡️ <b>Security Essentials</b>

                <b>✅ ONLY Enable These:</b>
                • Orders (place/cancel trades)
                • Positions (manage positions)
                • Trade (Derivatives & Spot)

                <b>❌ NEVER Enable These:</b>
                • Withdrawal (can steal funds!)
                • Account Transfer
                • Subaccount Transfer

                <b>🔒 IP Restriction (Recommended):</b>
                Add our server IP for extra security:
                <code>{$this->server_ip}</code>

                <b>Benefits:</b>
                • Keys never expire (no 90-day limit)
                • Extra security layer
                • Prevents unauthorized access

                <b>🛡️ Other Security:</b>
                • Enable 2FA on Bybit
                • Keep API keys private

                Need help adding IP restriction?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 IP Setup Guide', 'callback_data' => 'help_ip_setup_guid'],
                            ['text' => '📋 Copy Our IP', 'callback_data' => 'copy_server_ip'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }

        // Support
        else if($type == "support"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Support and Assistance</b>

                For help with setup or any questions, use our Support Bot.

                Our AI assistant is available 24/7 to help you with:
                - Complete onboarding walkthrough
                - Step by step tutorials
                - Risk management guidance
                - Troubleshooting issues

                If you need human assistance, you can open a support ticket directly in the bot. Our team typically responds within 24 hours.

                Tap below to open the Support Bot.
                EOT,
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💬 Open Support Bot', 'url' => 'https://t.me/SignalVision_bot'],
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']

                        ]
                    ]
                ])
            ]);
        }

        /*
        ==========================
        license
        ==========================
        */
        // license
        else if ($type == "license") {
            // const data  
            $expired_in = $user['expired_in'];

            // checking .. 
            if(is_null($expired_in)){
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🔑 License Activation</b>

                    Please enter your SignalShot license key to access automated trading features.

                    <b>💎 Not purchased yet?</b>
                    Visit <b>signalvision.ai/shop</b> to get your license.

                    <b>✨ Premium Features:</b>
                    - 🤖 Trade execution on Bybit
                    - 📊 Real-time trade monitoring
                    - ⚙️ Smart price adjustments
                    - 🔗 Works with SignalManager

                    Enter your license key to continue! 👇
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🌐 Go to Website', 'url' => 'https://signalvision.ai/trader'],
                            ],
                            [
                                ['text' => '🔙  Back to Menu', 'callback_data' => 'main_menu'],
                            ]
                        ]
                    ])
                ]);
            }else{
                $this->telegramMessageType("license_status", $chatId);
            }
        }
        // activated 
        else if ($type == "license_activated") {
            // const data  
            $type = $user["subscription_type"];
            $expired_in = $user["expired_in"];

            $expired = Carbon::parse($user->expired_in);
            $now = Carbon::now();
            $diffInHours = $now->diffInHours($expired, false);
            $diffInDays = $now->diffInDays($expired, false);

            if ($diffInHours <= 0) {
                $remaining = "Expired";
            } elseif ($diffInDays < 1) {
                $remaining = "{$diffInHours} hours";
            } else {
                $remaining = "{$diffInDays} days";
            }
            
            $response = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ License Activated Successfully!</b>

                Subscription Type: {$type}
                Expiration Date: {$expired_in}
                Days Remaining: {$remaining}

                <b>Now let's connect your Bybit accounts to start trading.</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔗 Connect Bybit', 'callback_data' => "bybit"]
                        ]
                    ]
                ])
            ]);

            $user->state = null;
            $user->save();

            // Run the artisan command
            Artisan::call('app:reset-wallet-balance');
        }
        // status  
        else if ($type == "license_status") {
            // const data  
            $expired_in = $user['expired_in'];
            $activation_in = $user['activation_in'];
            $period = $user['subscription_type'];

            $expired = Carbon::parse($expired_in);
            $now = Carbon::now();
            $diffInSeconds = $now->diffInSeconds($expired, false);
            $diffInHours = $now->diffInHours($expired, false);
            $diffInDays = $now->diffInDays($expired, false);

            if ($diffInSeconds <= 0) {
                $this->telegramMessageType("license_status_expired", $chatId);
                return;
            } elseif ($diffInDays < 1) {
                $remaining = "{$diffInHours}h {$diffInSeconds}s";
                $status = "Expiring Soon";
            } else {
                $remaining = "{$diffInDays} days";
                $status = "Active";
            }

            $response = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🔑 Your License Status</b>

                <b>Subscription Type:</b> {$period}
                <b>Activation Date:</b>
                {$activation_in}
                <b>Expiration Date:</b>
                {$expired_in}
                <b>Days Remaining:</b>
                {$remaining}

                <b>Status:</b> {$status}
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🆘 Help', 'callback_data' => 'help']
                        ],
                        [
                            ['text' => '🔄 Renew License', 'callback_data' => 'renew_license']
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "license_status_expired") {
            // const data  
            $expired_in = $user['expired_in'];
            $activation_in = $user['activation_in'];
            $period = $user['subscription_type'];
            
            $buttons[] = [['text' => '🔄 Renew License', 'callback_data' => 'renew_license']];

            $expired = Carbon::parse($expired_in);
            $now = Carbon::now();
            $diffInHours = $expired->diffInHours($now, false);
            $diffInDays = $expired->diffInDays($now, false);

            $response = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⏰ License Expired</b>

                Your license key is valid but has expired on {$expired}.

                <b>Subscription Details:</b>
                • Type: {$period}
                • Expired: {$diffInDays} days ago
                • Account: Active but restricted

                <b>💎 Renew your license:</b>
                Visit <b>signalvision.ai/shop</b> to continue using SignalShot.

                Your settings and API connections are saved!
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }

        /*
        ==========================
        Current Position 
        ==========================
        */
        else if ($type == "current_position_method") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Choose your exchange marketplace to check current positions.</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Bybit', 'callback_data' => 'current_position_bybit'],
                            ['text' => 'Binance', 'callback_data' => 'current_position_binance']
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "current_position_bybit") {
            $bybitAPI = new CryptoApiBybit();
            $response = $bybitAPI->listPosition($chatId);

            // balance 
            $wallet = $response["wallet"]["available_balance"] ?? 0;
            $available_balance = formatNumberFlexible($wallet, 2);
            $positions = $response["positions"] ?? null;

            // empty
            if(empty($positions)){
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📊 Current Positions on Bybit</b>

                    <b>💰 Account Balance:</b> {$available_balance} USDT

                    No open positions at the moment.

                    Start trading through SignalManager to see your positions here!
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'keyboard' => $mainButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ])
                ]);
                return;
            }

            $responsePositions = $positions;

            $positions = "";
            foreach ($responsePositions as $position) {
                $mod = $position['side'] == "Buy" ? "LONG" : "SHORT";
                $symbol = $mod === "LONG" ? "🟢" : "🔴";
                $pnlUsdt = formatNumberFlexible($position['unrealisedPnl'], 2);

                $positions .= <<<EOT
                {$symbol} {$position['symbol']} {$mod}
                ├ Entry: {$position['markPrice']}
                ├ Size: {$position['size']} {$position['symbol']} 
                ├ Margin: {$position['positionIM']} USDT
                ├ PnL: {$pnlUsdt} USDT
                └ Current Price: {$position['markPrice']}
                \n
                EOT;
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Current Positions on Bybit</b>
                <b>⚡ Available Balance:</b> {$available_balance} USDT

                $positions
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'keyboard' => $mainButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
        }
        else if ($type == "current_position_binance") {
            // contecting...
            $connectingMsg = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);
           
            $binanceAPI = app(CryptoApiBinance::class);
            $response = $binanceAPI->allPositions($chatId);

             // Delete "Connecting..." 
            $this->safeTelegramCall('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            // balance 
            $available_balance = $response["balance"];
            $positions = $response["positions"] ?? null;

            // empty
            if(empty($positions)){
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📊 Current Positions on Binance</b>

                    <b>💰 Account Balance:</b> {$available_balance} USDT

                    No open positions at the moment.

                    Start trading through SignalManager to see your positions here!
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'keyboard' => $mainButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ])
                ]);
                return;
            }

            $responsePositions = $positions;

            $positions = "";
            foreach ($responsePositions as $position) {
                $mod = $position['positionAmt'] > 0 ? "LONG" : "SHORT";
                $symbol = $mod === "LONG" ? "🟢" : "🔴";
                $pnlUsdt = formatNumberFlexible($position['unRealizedProfit'], 2);
                $markPrice = formatNumberFlexible($position['markPrice'], 2);
                $size = abs($position['positionAmt']);

                $positions .= <<<EOT
                {$symbol} {$position['symbol']} {$mod}
                ├ Entry: {$position['entryPrice']}
                ├ Size: {$size} {$position['symbol']} 
                ├ PnL: {$pnlUsdt} USDT
                └ Current Price: {$markPrice}
                \n
                EOT;
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Current Positions on Binance</b>
                <b>⚡ Available Balance:</b> {$available_balance} USDT

                $positions
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'keyboard' => $mainButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
        }

        /*
        ==========================
        API Method 
        ==========================
        */
        else if ($type == "select_api_keys_method") {
           $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Choose your exchange marketplace for auto trading. We require the API keys and secret key to enable the auto trading execution.</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Bybit', 'callback_data' => 'bybit'],
                            ['text' => 'Binance', 'callback_data' => 'binance']
                        ]
                    ]
                ])
            ]);
        }

        /*
        ==========================
        Binance APIs   
        ==========================
        */
        else if ($type == "binance") {
            $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
            $apiSecretHeader = env('API_SECRET');
            $response = Http::withHeaders([
                'API-SECRET' => $apiSecretHeader,
            ])->post("{$endpoint}/api/signal-shot/get-crypto-wallet", [
                'user_id'    => $chatId,
                'type'       => 'binance',
            ]);

            if (!$response->successful()) {
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>❌ Connection Failed</b>

                    Unable to connect to your Bybit account. Please check your API configuration.

                    <b>Common Issues:</b>
                    - Incorrect API keys
                    - Missing trading permissions
                    - IP restrictions blocking access
                    - Invalid API format

                    <b>Next Steps:</b>
                    1. Verify your API keys are correct
                    2. Check permissions in Bybit
                    3. Ensure IP restriction includes our server
                    4. Try reconnecting
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup']
                            ],
                            [
                                ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                            ]
                        ]
                    ])
                ]);
                return;
            }

            // const data  
            $status = $response['status'];
            $msg = $response['msg'];
            $available_balance = number_format($response['available_balance'], 2);
            
            // checking .. 
            if(1 == 2){
                
                return;

                if($hint === "API"){
                    if(!empty($msg)){
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => $msg,
                        ]);
                    }

                    // connect api 
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        🔧 <b>API Configuration</b>

                        Connect your Bybit API for live trading:

                        🚀 <b>Live API Setup</b>
                        - Execute real trades with real funds
                        - Works with real market prices
                        - Required for actual trading

                        🧪 <b>Demo Testing</b>
                        - Use SignalManager's demo mode
                        - Test strategies with real prices
                        - No separate API needed

                        Ready to connect your Bybit account?
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup']
                                ],
                                [
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
                // API CONNECTIOn 
                else if($hint === "API_CONNECTION"){
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>❌ Connection Failed</b>

                        Unable to connect to your Bybit account. Please check your API configuration.

                        <b>Common Issues:</b>
                        - Incorrect API keys
                        - Missing trading permissions
                        - IP restrictions blocking access
                        - Invalid API format

                        <b>Next Steps:</b>
                        1. Verify your API keys are correct
                        2. Check permissions in Bybit
                        3. Ensure IP restriction includes our server
                        4. Try reconnecting
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
                else{
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => $msg,
                    ]);
                }
            }

            $this->telegramMessageType("binance_balance", $chatId, ["available_balance" => $available_balance]);
        }
        else if ($type == "binance_balance") {
            $available_balance = $data["available_balance"];

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Your binance account is now connected to SignalShot.</b>

                <b>Account Overview:</b>
                📊 <b>Account Balance:</b> {$available_balance} USDT

                You can now use SignalManagement to place real trades automatically.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Restart', 'callback_data' => 'binance_api_setup'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "binance_api_video_tutorial"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🎥 <b>API Setup Video Tutorial</b>

                Complete walkthrough of Binance API setup:

                [VIDEO EMBEDDED OR LINK]

                💡 <b>Quick Steps:</b>
                1️⃣ Create Binance account  
                2️⃣ Generate API keys  
                3️⃣ Add to SignalShot  
                4️⃣ Start trading!

                💡 <b>Demo Testing:</b>  
                Use SignalManager's demo mode for safe strategy testing with real market prices.

                Watch first, then setup!
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Setup Now', 'callback_data' => 'binance_api_setup'],
                            ['text' => '📞 Need Help?', 'callback_data' => 'help'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "binance_api_setup"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🚀 <b>Live API Setup (Real Trading)</b>

                Connect your Binance account for real trading:

                <b>Quick Setup:</b>
                1️⃣ Visit: <b>binance.com</b>  
                2️⃣ Login & generate API keys  
                3️⃣ Add IP restriction (recommended)  
                4️⃣ Add your keys using buttons below

                🔒 <b>For Enhanced Security:</b>  
                Add our IP when creating API keys:  
                <code>{$this->server_ip}</code>

                ⚠️ <b>Real Trading Means:</b>  
                • Actual money at risk  
                • Start with small amounts

                🧪 <b>Test First:</b>  
                Use SignalManager demo mode!

                Ready to connect?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Copy Our IP', 'callback_data' => "copy_server_ip"],
                            ['text' => '🌐 Open Binance Website', 'url' => 'https://www.binance.com'],
                        ],
                        [
                            ['text' => '🔑 Add API', 'callback_data' => 'binance_api_credentials'],
                            ['text' => '🎥 Watch Setup Video', 'callback_data' => 'binance_api_video_tutorial'],
                        ],
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                        ]
                    ]
                ])
            ]);
        }

        /*
        ==========================
        Bybit APIs   
        ==========================
        */
        else if ($type == "bybit") {
            // contecting...
            $connectingMsg = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
            $apiSecretHeader = env('API_SECRET');

            $response = Http::withHeaders([
                'API-SECRET' => $apiSecretHeader,
            ])->post("{$endpoint}/api/signal-shot/get-crypto-wallet", [
                'user_id'    => $chatId,
                'type'       => 'bybit',
            ]);

            // Delete API secret message
            $this->safeTelegramCall('deleteMessage', [
                'chat_id'    => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            if (!$response->successful()) {
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>❌ Connection Failed</b>

                    Unable to connect to your Bybit account. Please check your API configuration.

                    <b>Common Issues:</b>
                    - Incorrect API keys
                    - Missing trading permissions
                    - IP restrictions blocking access
                    - Invalid API format

                    <b>Next Steps:</b>
                    1. Verify your API keys are correct
                    2. Check permissions in Bybit
                    3. Ensure IP restriction includes our server
                    4. Try reconnecting
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                            ]
                        ]
                    ])
                ]);
                return;
            }

            // const data  
            $status = $response['status'];
            $msg = $response['msg'] ?? null;
            $available_balance = number_format($response['available'] ?? 0, 2);
            $hint = '';

            // checking .. 
            if(!$status){
                if($hint === "API"){
                    if(!empty($msg)){
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => $msg,
                        ]);
                    }

                    // connect api 
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        🔧 <b>API Configuration</b>

                        Connect your Bybit API for live trading:

                        🚀 <b>Live API Setup</b>
                        - Execute real trades with real funds
                        - Works with real market prices
                        - Required for actual trading

                        🧪 <b>Demo Testing</b>
                        - Use SignalManager's demo mode
                        - Test strategies with real prices
                        - No separate API needed

                        Ready to connect your Bybit account?
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
                // API CONNECTIOn 
                else if($hint === "API_CONNECTION"){
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>❌ Connection Failed</b>

                        Unable to connect to your Bybit account. Please check your API configuration.

                        <b>Common Issues:</b>
                        - Incorrect API keys
                        - Missing trading permissions
                        - IP restrictions blocking access
                        - Invalid API format

                        <b>Next Steps:</b>
                        1. Verify your API keys are correct
                        2. Check permissions in Bybit
                        3. Ensure IP restriction includes our server
                        4. Try reconnecting
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                ],
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
                else{
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>❌ Connection Failed</b>

                        {$msg}

                        <b>Common Issues:</b>
                        - Incorrect API keys
                        - Missing trading permissions
                        - IP restrictions blocking access
                        - Invalid API format

                        <b>Next Steps:</b>
                        1. Verify your API keys are correct
                        2. Check permissions in Bybit
                        3. Ensure IP restriction includes our server
                        4. Try reconnecting
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                ],
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
            }else{
                $this->telegramMessageType("bybit_balance", $chatId, ["available_balance" => $available_balance]);
            }
        }
        else if ($type == "bybit_balance") {
            $available_balance = $data["available_balance"];

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Your trading account is now connected to SignalShot.</b>

                <b>Account Overview:</b>
                📊 <b>Account Balance:</b> {$available_balance} USDT

                You can now use SignalManagement to place real trades automatically.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Restart', 'callback_data' => 'bybit_api_setup'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "bybit_api_video_tutorial"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🎥 <b>API Setup Video Tutorial</b>

                Complete walkthrough of Bybit API setup:

                [VIDEO EMBEDDED OR LINK]

                💡 <b>Quick Steps:</b>
                1️⃣ Create Bybit account  
                2️⃣ Generate API keys  
                3️⃣ Add to SignalShot  
                4️⃣ Start trading!

                💡 <b>Demo Testing:</b>  
                Use SignalManager's demo mode for safe strategy testing with real market prices.

                Watch first, then setup!
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Setup Now', 'callback_data' => 'bybit_api_setup'],
                            ['text' => '📞 Need Help?', 'callback_data' => 'help'],
                        ],
                        [
                            ['text' => '🔙 Back to Menu', 'callback_data' => 'main_menu']
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "bybit_api_setup"){
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                🚀 <b>Live API Setup (Real Trading)</b>

                Connect your Bybit account for real trading:

                <b>Quick Setup:</b>
                1️⃣ Visit: <b>bybit.com</b>  
                2️⃣ Login & generate API keys  
                3️⃣ Add IP restriction (recommended)  
                4️⃣ Add your keys using buttons below

                🔒 <b>For Enhanced Security:</b>  
                Add our IP when creating API keys:  
                <code>{$this->server_ip}</code>

                ⚠️ <b>Real Trading Means:</b>  
                • Actual money at risk  
                • Start with small amounts

                🧪 <b>Test First:</b>  
                Use SignalManager demo mode!

                Ready to connect?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Copy Our IP', 'callback_data' => "copy_server_ip"],
                            ['text' => '🌐 Open Bybit Website', 'url' => 'https://www.bybit.com'],
                        ],
                        [
                            ['text' => '🔑 Add API', 'callback_data' => 'bybit_api_credentials'],
                            ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                        ]
                    ]
                ])
            ]);
        }

        /* 
        ========================== 
        💰 Risk Management 
        ==========================
        */
        else if ($type == "money_management") {
            $type = $user->money_management_type;
            if($type === "demo" && !isset($data["wallet_balance"])){
                $walletBalance = $user->money_management_demo_wallet_balance;
                $this->telegramMessageType("money_management_view", $chatId, ["wallet_balance" => $walletBalance, "type" => "demo"]);
            }else if($user->state === "bybit_method"){
                $walletBalance = $user->money_management_bybit_wallet_balance;
                $this->telegramMessageType("money_management_view", $chatId, ["wallet_balance" => $walletBalance, "type" => "Bybit"]);
            }else if($user->state === "binance_method"){
                $walletBalance = $user->money_management_binance_wallet_balance;
                $this->telegramMessageType("money_management_view", $chatId, ["wallet_balance" => $walletBalance, "type" => "Binance"]);
            }else{
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>Choose your exchange marketplace for Risk Management.</b>
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => 'Bybit', 'callback_data' => 'bybit_method_money_management'],
                                ['text' => 'Binance', 'callback_data' => 'binance_method_money_management']
                            ]
                        ]
                    ])
                ]);
            }
        }
        else if ($type === "money_management_view") {
            $walletBalance  = (float) ($data['wallet_balance'] ?? 0);
            $accountTypeRaw = $data['type'] ?? 'real';

            $riskIsActive   = ($user['money_management_risk_status'] ?? 'inactive') === 'active';
            $isActive   = ($user['money_management_status'] ?? 'inactive') === 'active';
            $isReal     = ($user['money_management_type'] ?? 'real') === 'real';
            $riskPct    = (float) ($user['money_management_risk'] ?? 0);
            $leverage    = (float) ($user['uni_leverage_money_management'] ?? 1); 

            // risk amount 
            $riskAmount = number_format(($riskPct / 100) * $walletBalance, 2, '.', '');

            $isRiskAmount = $riskIsActive
                ? $riskAmount . "USDT"
                : "❌ Inactive";

            $isRiskAmountP = $riskIsActive
                ? $riskPct . "%"
                : "❌ Inactive";

            // leverage 
            $leverage = $user['money_management_uni_leverage_status'] === 'active'
                ? $user['money_management_uni_leverage']."X"
                : "Inactive";

            // strategy
            $uniStrategy = $user['money_management_uni_strategy_status'] === 'inactive'
                ? "Not Set"
                : ucfirst($user['money_management_uni_strategy_status'])." Mode";
            $autoBot = $user['money_management_uni_strategy_status'] === 'active'
                ? "Active"
                : "Inactive";

            // Format labels
            $typeLabel = $accountTypeRaw === 'demo'
                ? "<b>🔍 Type:</b> Demo"
                : "<b>✅ Type:</b> {$accountTypeRaw}";

            $statusLabel = $isActive
                ? "<b>✅ Status:</b> Active"
                : "<b>❌ Status:</b> Inactive";

            // Button row builder
            $row = static fn(array ...$buttons) => collect($buttons)->map(fn($b) => ['text' => $b[0], 'callback_data' => $b[1]])->all();

            // Common buttons
            $buttons = [
                $row(['⚙️ Configure Risk', 'configure_risk_money_management'], ['📊 Risk Analysis', 'risk_analysis_money_management']),
                $row(['🛡️ Safety Rules', 'safety_rules_money_management'], ['🔄 Toggle On/Off', 'toggle_money_management']),
                $row(['📖 Help', 'help_money_management'], ['🔙 Back', 'main_menu']),
                $row(['🎯 Uni. Leverage', 'uni_leverage_money_management'], ['🎲 Uni. Strategy', 'uni_strategy_money_management']),
            ];

            if ($isReal) {
                $buttons[] = $row(['🔄 Switch To Demo Trade', 'toggle_type_money_management']);
                $buttons[] = $row(['♻️ Refresh Wallet Balance', 'refresh_wallet_money_management']);
            } else {
                $buttons[] = $row(['💸 Demo Balance', 'demo_balance_money_management'], ['🔄 Switch To Real', 'toggle_type_money_management']);
            }

            // Message body
            $msg = <<<EOT
            <b>💰 Risk Management Settings</b>

            <b>Current Configuration:</b>
            {$typeLabel}
            {$statusLabel}
            <b>💼 Base Balance:</b> {$walletBalance} USDT (Daily)
            <b>📊 Risk per Trade:</b> {$isRiskAmountP}
            <b>🎯 Max Risk per Trade:</b> {$isRiskAmount}
            <b>⏰ Reset Time:</b> 00:00 UTC
            <b>⚡ Universal Leverage:</b> {$leverage}
            <b>🎲 Universal Strategy:</b> {$uniStrategy}
            <b>🤖 Auto-Trade:</b> {$autoBot}
            EOT;

            $msg .= "\n\nSelect an option:";

            // Send Telegram message
            $this->safeTelegramCall('sendMessage', [
                'chat_id'      => $chatId,
                'text'         => $msg,
                'parse_mode'   => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
            ]);
        }

        // Configure Risk
        else if ($type == "configure_risk_money_management") {
            $buttons = [
                [
                    ['text' => '0.5%', 'callback_data' => "percentage_configure_risk_money_management_0.5"],
                    ['text' => '1%', 'callback_data' => "percentage_configure_risk_money_management_1"],
                    ['text' => '1.5%', 'callback_data' => "percentage_configure_risk_money_management_1.5"],
                ],
                [
                    ['text' => '2%', 'callback_data' => "percentage_configure_risk_money_management_2"],
                    ['text' => '3%', 'callback_data' => "percentage_configure_risk_money_management_3"],
                    ['text' => '5%', 'callback_data' => "percentage_configure_risk_money_management_5"],
                ],
                [
                    ['text' => '✏️ Custom', 'callback_data' => 'custom_percentage_configure_risk_money_management'],
                    ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                ]
            ];

            if($user["money_management_risk_status"] === "active"){
                $buttons[] = [
                    ['text' => '❌ Yes, Disable?', 'callback_data' => 'disable_enable_risk_money_management']
                ];
            }else{
                $buttons[] = [
                    ['text' => '✅ Yes, Enable?', 'callback_data' => 'disable_enable_risk_money_management'],
                ];
            }
            
            $status = $user["money_management_risk_status"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Inactive";

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚙️ Risk Configuration</b>

                How much of your account do you want to risk per trade?

                <b>✅ Current:</b> {$user["money_management_risk"]}%
                {$status}

                Select risk percentage:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }
        else if ($type == "custom_percentage_configure_risk_money_management") {
            $status = $user["money_management_status"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Inactive";

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✏️ Custom Risk Percentage</b>

                Enter your desired risk per trade (0.1% - 10%):
                <b>💡 Recommended:</b> 1-3% for safety

                <b>✅ Current:</b> {$user["money_management_risk"]}%
                {$status}
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '❌ Cancel', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "confirm_configure_risk_money_management") {
            $status = $user["money_management_status"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Inactive";

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ Risk Updated</b>

                <b>✅ Current:</b> {$user["money_management_risk"]}%
                {$status}

                This means each trade will risk maximum of your account.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }

        // uni lev  
        else if ($type == "uni_leverage_money_management") {
            if($user["money_management_uni_leverage_status"] === "active"){
                $status = "<b>✅ Status:</b> Active";
                $btnTxt = "❌ Turn Off";
            }else{
                $status = "<b>❌ Status:</b> Inactive";
                $btnTxt = "✅ Turn On";
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 Universal Leverage  </b>

                What leverage multiplier do you want to use for all trades?

                <b>✅ Current:</b> {$user["money_management_uni_leverage"]}X
                {$status}

                Select risk percentage:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '1X', 'callback_data' => "percentage_uni_leverage_money_management_1"],
                            ['text' => '2X', 'callback_data' => "percentage_uni_leverage_money_management_2"],
                            ['text' => '5X', 'callback_data' => "percentage_uni_leverage_money_management_5"],
                        ],
                        [
                            ['text' => '10X', 'callback_data' => "percentage_uni_leverage_money_management_10"],
                            ['text' => '20X', 'callback_data' => "percentage_uni_leverage_money_management_20"],
                            ['text' => '50X', 'callback_data' => "percentage_uni_leverage_money_management_50"],
                            // ['text' => '75X', 'callback_data' => "percentage_uni_leverage_money_management_75"],
                        ],
                        [
                            ['text' => '✏️ Custom', 'callback_data' => 'custom_percentage_uni_leverage_money_management'],
                            ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                        ],
                        [
                            ['text' => $btnTxt, 'callback_data' => 'toggle_uni_leverage_money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "custom_percentage_uni_leverage_money_management") {
            $status = $user["money_management_uni_leverage_status"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Inactive";

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 Universal Leverage</b>

                Enter your desired risk per trade (5x - 10x):

                <b>✅ Current:</b> {$user["money_management_uni_leverage"]}X
                {$status}
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '❌ Cancel', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "confirm_uni_leverage_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 Universal Leverage Updated</b>

                <b>✅ Current:</b> {$user["money_management_uni_leverage"]}X
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }

        // uni strategy
        else if ($type == "uni_strategy_money_management") {
            if($user["money_management_uni_strategy_status"] === "inactive"){
                $mode = "Not Selected";
                $status = "<b>❌ Status:</b> Inactive";
            }else{
                $status = "<b>✅ Status:</b> Active";
                $mode = ucfirst($user["money_management_uni_strategy_status"]);
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎲 Universal Strategy Settings</b>

                <b>Current Configuration:</b>
                <b>🎯 Current Mode:</b> {$mode}

                <b>Description:</b>
                Configure how signals will be processed automatically using your predefined strategy settings.

                Select an option:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '⚙️ Config', 'callback_data' => "config_uni_strategy_money_management"],
                            ['text' => '🎯 Mode', 'callback_data' => "mod_uni_strategy_money_management"],
                        ],
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "mod_uni_strategy_money_management") {
            if($user["money_management_uni_strategy_status"] != "inactive"){
                $mode = ucfirst($user["money_management_uni_strategy_status"]);
                $buttons = [
                    [
                        ['text' => '🟡 Passive Mode', 'callback_data' => "mod_uni_strategy_money_management_passive"],
                        ['text' => '🟢 Active Mode', 'callback_data' => "mod_uni_strategy_money_management_active"],
                    ],
                    [
                        ['text' => '❌ Deactivate Now', 'callback_data' => 'mod_uni_strategy_money_management_inactive'],
                        ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management'],
                    ]
                ];
            }else{
                $mode = "Not Selected";
                $buttons = [
                    [
                        ['text' => '🟡 Passive Mode', 'callback_data' => "mod_uni_strategy_money_management_passive"],
                        ['text' => '🟢 Active Mode', 'callback_data' => "mod_uni_strategy_money_management_active"],
                    ],
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management'],
                    ]
                ];
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎲 Universal Strategy Mode Settings</b>

                <b>🎯 Current Mode:</b> {$mode}

                <b>🟡 Passive Mode</b> - Receive signal → Clicks "Start Tracking"
                <b>🟢 Active Mode</b> - Receive signal → Auto-execute trade
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }

        // Risk Analysis
        else if ($type == "risk_analysis_money_management") {
            // demo 
            $type = ucwords($user->money_management_type);
            if($type === "Demo"){
                $this->telegramMessageType("demo_risk_analysis_money_management", $chatId);
            }

            // real 
            else if($user->state === "binance_method"){
                $this->telegramMessageType("binance_risk_analysis_money_management", $chatId);
            }else{
                $this->telegramMessageType("bybit_risk_analysis_money_management", $chatId);
            }
        }
        else if ($type == "bybit_risk_analysis_money_management") {
            // contecting...
            $connectingMsg = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
            $apiSecretHeader = env('API_SECRET');

            $response = Http::withHeaders([
                'API-SECRET' => $apiSecretHeader,
            ])->post("{$endpoint}/api/signal-shot/get-crypto-wallet", [
                'user_id'    => $chatId,
                'type'       => 'bybit',
            ]);

            // Delete API secret message
            $this->safeTelegramCall('deleteMessage', [
                'chat_id'    => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            if (!$response->successful()) {
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>❌ Connection Failed</b>

                    Unable to connect to your Bybit account. Please check your API configuration.

                    <b>Common Issues:</b>
                    - Incorrect API keys
                    - Missing trading permissions
                    - IP restrictions blocking access
                    - Invalid API format

                    <b>Next Steps:</b>
                    1. Verify your API keys are correct
                    2. Check permissions in Bybit
                    3. Ensure IP restriction includes our server
                    4. Try reconnecting
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                            ],
                            [
                                ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                            ]
                        ]
                    ])
                ]);
                return;
            }

            // const data  
            $status = $response['status'];
            $msg = $response['msg'] ?? null;
            $available_balance = number_format($response['available'] ?? 0, 2);
            $hint = '';
            
            // checking .. 
            if(!$status){
                if($hint === "API"){
                    if(!empty($msg)){
                        $this->safeTelegramCall('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => $msg,
                        ]);
                    }

                    // connect api 
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        🔧 <b>API Configuration</b>

                        Connect your Bybit API for live trading:

                        🚀 <b>Live API Setup</b>
                        - Execute real trades with real funds
                        - Works with real market prices
                        - Required for actual trading

                        🧪 <b>Demo Testing</b>
                        - Use SignalManager's demo mode
                        - Test strategies with real prices
                        - No separate API needed

                        Ready to connect your Bybit account?
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
                // API CONNECTIOn 
                else if($hint === "API_CONNECTION"){
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>❌ Connection Failed</b>

                        Unable to connect to your Bybit account. Please check your API configuration.

                        <b>Common Issues:</b>
                        - Incorrect API keys
                        - Missing trading permissions
                        - IP restrictions blocking access
                        - Invalid API format

                        <b>Next Steps:</b>
                        1. Verify your API keys are correct
                        2. Check permissions in Bybit
                        3. Ensure IP restriction includes our server
                        4. Try reconnecting
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'bybit_api_setup'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
                }
                else{
                    $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => $msg,
                    ]);
                }
            }else{
                $response = Http::withHeaders([
                    'API-SECRET' => env("API_SECRET")
                ])->get(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/active-real-positions', [
                    "user_id" => $chatId,
                    "market" => "bybit"
                ]);

                // active lists  
                $lists = "";
                foreach ($response["lists"] as $index=> $value) {
                    $counter = $index+1;
                    $lists .= <<<EOT
                    <b>{$counter}️⃣ {$value['instruments']} {$value['tp_mode']}</b>
                    <b>Risk:</b> {$value['risk_amount']} USDT ({$value['risk_percentage']}% of account)
                    <b>Stop Loss:</b> {$value['loss_ercentage']}%\n\n
                    EOT;
                }

                // check empty 
                if(empty($response["lists"])){
                    $lists = "No active position found!";
                }

                $resInfo = $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📊 Risk Analysis for Bybit</b>

                    <b>💼 Wallet Balance:</b> {$user->money_management_wallet_balance} USDT (Today)
                    <b>⚡ Available Balance:</b> {$available_balance} USDT
                    <b>🔥 Current Exposure:</b> {$response["current_exposure_amount"]} USDT ({$response["current_exposure_percentage"]}%)

                    <b>Active Positions Risk:</b>
                    {$lists}
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 Refresh', 'callback_data' => 'risk_analysis_money_management'],
                                ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                            ]
                        ]
                    ])
                ]);

                try {
                    $riskAnalysisMsgID = Cache::get('money_management_risk_analysis'.$user->id);
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $riskAnalysisMsgID,
                    ]);
                } catch (\Throwable $th) {
                }
                // add new ids  
                Cache::forever('money_management_risk_analysis'.$user->id, $resInfo->getMessageId());
            }
        }
        else if ($type == "binance_risk_analysis_money_management") {
            #binance
            $binanceAPI = app(CryptoApiBinance::class);
            $responseBinance = $binanceAPI->getBalance($chatId);

            // const data  
            $status = $responseBinance['status'];
            $hint = $responseBinance['hint'] ?? null;
            $msg = $responseBinance['msg'];
            
            // checking .. 
            if(!$status){
                $this->safeTelegramCall('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>❌ Connection Failed</b>

                        Unable to connect to your Binance account. Please check your API configuration.

                        <b>Common Issues:</b>
                        - Incorrect API keys
                        - Missing trading permissions
                        - IP restrictions blocking access
                        - Invalid API format

                        <b>Next Steps:</b>
                        1. Verify your API keys are correct
                        2. Check permissions in Binance
                        3. Ensure IP restriction includes our server
                        4. Try reconnecting
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🚀 Setup API', 'callback_data' => 'binance_api_setup']
                                ],
                                [
                                    ['text' => '🎥 Video Tutorial', 'callback_data' => 'binance_api_video_tutorial'],
                                    ['text' => '🔙 Back', 'callback_data' => 'main_menu'],
                                ]
                            ]
                        ])
                    ]);
            }else{
                $available_balance = number_format($responseBinance['available_balance'], 2, '.', '');
                $response = Http::withHeaders([
                    'API-SECRET' => env("API_SECRET")
                ])->get(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/active-real-positions', [
                    "user_id" => $chatId,
                    "market" => "binance"
                ]);

                // active lists  
                $lists = "";
                foreach ($response["lists"] as $index=> $value) {
                    $counter = $index+1;
                    $lists .= <<<EOT
                    <b>{$counter}️⃣ {$value['instruments']} {$value['tp_mode']}</b>
                    <b>Risk:</b> {$value['risk_amount']} USDT ({$value['risk_percentage']}% of account)
                    <b>Stop Loss:</b> {$value['loss_ercentage']}%\n\n
                    EOT;
                }

                // check empty 
                if(empty($response["lists"])){
                    $lists = "No active position found!";
                }

                $resInfo = $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📊 Risk Analysis for Binance</b>

                    <b>💼 Wallet Balance:</b> {$user->money_management_binance_wallet_balance} USDT (Today)
                    <b>⚡ Available Balance:</b> {$available_balance} USDT
                    <b>🔥 Current Exposure:</b> {$response["current_exposure_amount"]} USDT ({$response["current_exposure_percentage"]}%)

                    <b>Active Positions Risk:</b>
                    {$lists}
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 Refresh', 'callback_data' => 'risk_analysis_money_management'],
                                ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                            ]
                        ]
                    ])
                ]);

                try {
                    $riskAnalysisMsgID = Cache::get('money_management_risk_analysis'.$user->id);
                    $this->safeTelegramCall('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $riskAnalysisMsgID,
                    ]);
                } catch (\Throwable $th) {
                }
                // add new ids  
                Cache::forever('money_management_risk_analysis'.$user->id, $resInfo->getMessageId());
            }
        }
        else if ($type == "demo_risk_analysis_money_management") {
            $response = Http::withHeaders([
                'API-SECRET' => env("API_SECRET")
            ])->get(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/active-demo-positions', [
                "user_id" => $chatId
            ]);

            // active lists  
            $lists = "";
            foreach ($response["lists"] as $index=> $value) {
                $counter = $index+1;
                $lists .= <<<EOT
                <b>{$value['instruments']} {$value['tp_mode']}</b>
                <b>Risk:</b> {$value['risk_amount']} USDT ({$value['risk_percentage']}% of account)
                <b>Stop Loss:</b> {$value['loss_ercentage']}%
                <b>PnL:</b> {$value['current_pnl']} USDT\n\n
                EOT;
            }
            $available_balance = $user->money_management_demo_available_balance + $response["pnl"];

            // check empty 
            if(empty($response["lists"])){
                $lists = "No active position found!";
            }

            $resInfo = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Risk Analysis for Demo Trade</b>

                <b>💼 Wallet Balance:</b> {$user->money_management_wallet_balance} USDT (Today)
                <b>⚡ Available Balance:</b> {$available_balance} USDT
                <b>🔥 Current Exposure:</b> {$response["current_exposure_amount"]} USDT ({$response["current_exposure_percentage"]}%)
                <b>🔥 Current PnL:</b> {$response["pnl"]} USDT

                <b>Active Positions Risk:</b>
                {$lists}
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Refresh', 'callback_data' => 'risk_analysis_money_management'],
                            ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);

            try {
                $riskAnalysisMsgID = Cache::get('money_management_risk_analysis'.$user->id);
                $this->safeTelegramCall('deleteMessage', [
                    'chat_id' => $chatId,
                    'message_id' => $riskAnalysisMsgID,
                ]);
            } catch (\Throwable $th) {
            }
            // add new ids  
            Cache::forever('money_management_risk_analysis'.$user->id, $resInfo->getMessageId());
        }

        // Safety Rules 
        else if ($type == "safety_rules_money_management") { 
            $max_exposure = $user["money_management_status_max_exposure"] === "active" ? "<b>✅ Max Account Exposure: </b>". $user['money_management_max_exposure']."%" : "<b>❌ Max Account Exposure:</b> Disabled";
            $trade_limit = $user["money_management_status_trade_limit"] === "active" ? "<b>✅ Max Concurrent Trades: </b>". $user['money_management_trade_limit'] : "<b>❌ Max Concurrent Trades:</b> Disabled";
            $stop_trades = $user["money_management_status_stop_trades"] === "active" ? "<b>✅ Stop New Trades at: </b>". $user['money_management_stop_trades']."%": "<b>❌ Stop New Trades at:</b> Disabled";
            $daily_loss = $user["money_management_status_daily_loss"] === "active" ? "<b>✅ Daily Loss Limit: </b>". $user['money_management_daily_loss']."%" : "<b>❌ Daily Loss Limit:</b> Disabled";

            // btn status 
            if($user["money_management_status_max_exposure"] === "active" && $user["money_management_status_trade_limit"] === "active" && $user["money_management_status_stop_trades"] === "active" && $user["money_management_status_daily_loss"] === "active"){
                $deactiveActiveTxt = "❌ Disable All";
                $deactiveActiveBtn = "disable_all_safety_rules_money_management";
            }else{
                $deactiveActiveTxt = '✅ Enable All';
                $deactiveActiveBtn = "enable_all_safety_rules_money_management";
            }

            $buttons = [
                [
                    ['text' => '📊 Max Exposure', 'callback_data' => 'max_exposure_safety_rules_money_management'],
                    ['text' => '🎯 Trade Limit', 'callback_data' => 'trade_limit_safety_rules_money_management'],
                ],
                [
                    // ['text' => '🛑 Stop Trades', 'callback_data' => 'stop_trades_safety_rules_money_management'],
                    ['text' => '🛑 Daily Loss', 'callback_data' => 'daily_loss_safety_rules_money_management'],
                    ['text' => $deactiveActiveTxt, 'callback_data' => $deactiveActiveBtn],
                ],
                [
                    
                    ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                ]
            ];

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🛡️ Safety Rules Configuration</b>

                <b>Current Rules:</b>
                {$max_exposure}
                {$trade_limit}
                {$daily_loss}

                Configure rules:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }
        
        else if ($type == "max_exposure_safety_rules_money_management") {
            $status = $user["money_management_status_max_exposure"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Disable";

            $buttons = [
                [
                    ['text' => '10%', 'callback_data' => 'percentage_max_exposure_safety_rules_money_management_10'],
                    ['text' => '15%', 'callback_data' => 'percentage_max_exposure_safety_rules_money_management_15'],
                    ['text' => '20%', 'callback_data' => 'percentage_max_exposure_safety_rules_money_management_20'],
                ],
                [
                    ['text' => '25%', 'callback_data' => 'percentage_max_exposure_safety_rules_money_management_25'],
                    ['text' => '30%', 'callback_data' => 'percentage_max_exposure_safety_rules_money_management_30'],
                    ['text' => '35%', 'callback_data' => 'percentage_max_exposure_safety_rules_money_management_35'],
                ],
                [
                    ['text' => '✏️ Custom', 'callback_data' => 'custom_percentage_max_exposure_safety_rules_money_management'],
                    ['text' => '🔙 Back', 'callback_data' => 'safety_rules_money_management'],
                ],
                [
                ]
            ];

            if($user["money_management_status_max_exposure"] === "active"){
                $buttons[] = [
                    ['text' => '❌ Yes, Disable?', 'callback_data' => 'disable_enable_max_exposure_safety_rules_money_management']
                ];
            }else{
                $buttons[] = [
                    ['text' => '✅ Yes, Enable?', 'callback_data' => 'disable_enable_max_exposure_safety_rules_money_management'],
                ];
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Maximum Account Exposure</b>

                What's the maximum % of your account you want at risk at any time?

                <b>✅ Current:</b> {$user["money_management_max_exposure"]}%
                {$status}

                Recommended: 10-20%
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }
        else if ($type == "custom_percentage_max_exposure_safety_rules_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✏️ Custom Maximum Exposure</b>

                Enter maximum exposure percentage (5-50%):
                <b>💡 Recommended:</b> 15-25% for safety
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '❌ Cancel', 'callback_data' => 'safety_rules_money_management'],
                        ]
                    ]
                ])
            ]);
        }

        // demo balance  
        else if ($type == "demo_balance_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✏️ Custom Account Balance</b>

                Enter your account demo Balance:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '❌ Cancel', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "trade_limit_safety_rules_money_management") {
            $status = $user["money_management_status_trade_limit"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Disable";
            $buttons = [
                [
                    ['text' => '2', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_2'],
                    ['text' => '3', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_3'],
                    ['text' => '4', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_4'],
                ],
                [
                    ['text' => '5', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_5'],
                    ['text' => '6', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_6'],
                    ['text' => '7', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_7'],
                ],
                [
                    ['text' => '8', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_8'],
                    ['text' => '9', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_9'],
                    ['text' => '10', 'callback_data' => 'limit_trade_limit_safety_rules_money_management_10'],
                ],
                [
                    ['text' => '🔙 Back', 'callback_data' => 'safety_rules_money_management'],
                ]
            ];

            if($user["money_management_status_trade_limit"] === "active"){
                $buttons[] = [
                    ['text' => '❌ Yes, Disable?', 'callback_data' => 'disable_enable_trade_limit_safety_rules_money_management']
                ];
            }else{
                $buttons[] = [
                    ['text' => '✅ Yes, Enable?', 'callback_data' => 'disable_enable_trade_limit_safety_rules_money_management'],
                ];
            }
            
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 Maximum Concurrent Trades</b>

                How many trades can be open at the same time?

                <b>✅ Current:</b> {$user["money_management_trade_limit"]}
                {$status}

                This helps prevent overexposure to market risk.

                Select maximum concurrent trades:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }

        else if ($type == "stop_trades_safety_rules_money_management") {
            $status = $user["money_management_status_stop_trades"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Disable";
            $buttons = [
                [
                    ['text' => '70%', 'callback_data' => 'percentage_stop_trades_safety_rules_money_management_70'],
                    ['text' => '75%', 'callback_data' => 'percentage_stop_trades_safety_rules_money_management_75'],
                    ['text' => '80%', 'callback_data' => 'percentage_stop_trades_safety_rules_money_management_80'],
                ],
                [
                    ['text' => '85%', 'callback_data' => 'percentage_stop_trades_safety_rules_money_management_85'],
                    ['text' => '90%', 'callback_data' => 'percentage_stop_trades_safety_rules_money_management_90'],
                    ['text' => '95%', 'callback_data' => 'percentage_stop_trades_safety_rules_money_management_95'],
                ],
                [
                    ['text' => '✏️ Custom', 'callback_data' => 'custom_percentage_stop_trades_safety_rules_money_management'],
                    ['text' => '🔙 Back', 'callback_data' => 'safety_rules_money_management'],
                ]
            ];

            if($user["money_management_status_stop_trades"] === "active"){
                $buttons[] = [
                    ['text' => '❌ Yes, Disable?', 'callback_data' => 'disable_enable_stop_trades_safety_rules_money_management']
                ];
            }else{
                $buttons[] = [
                    ['text' => '✅ Yes, Enable?', 'callback_data' => 'disable_enable_stop_trades_safety_rules_money_management'],
                ];
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Stop New Trades Threshold</b>

                When should we warn about approaching max exposure?

                <b>✅ Current:</b> {$user["money_management_stop_trades"]}%
                {$status}

                (Will stop new trades at 16% total risk)

                Select warning threshold:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }
        else if ($type == "custom_percentage_stop_trades_safety_rules_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✏️ Custom Stop New Trades Threshold</b>

                Enter maximum number of concurrent trades (1%-100%):
                <b>💡 Recommended:</b> 70%-80 for most traders
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '❌ Cancel', 'callback_data' => 'safety_rules_money_management'],
                        ]
                    ]
                ])
            ]);
        }

        else if ($type == "daily_loss_safety_rules_money_management") {
            $status = $user["money_management_status_daily_loss"] === "active" ? "<b>✅ Status:</b> Active" : "<b>❌ Status:</b> Disable";
            $buttons = [
                [
                    ['text' => '2%', 'callback_data' => 'percentage_daily_loss_safety_rules_money_management_2'],
                    ['text' => '3%', 'callback_data' => 'percentage_daily_loss_safety_rules_money_management_3'],
                    ['text' => '4%', 'callback_data' => 'percentage_daily_loss_safety_rules_money_management_4'],
                ],
                [
                    ['text' => '5%', 'callback_data' => 'percentage_daily_loss_safety_rules_money_management_5'],
                    ['text' => '6%', 'callback_data' => 'percentage_daily_loss_safety_rules_money_management_6'],
                    ['text' => '7%', 'callback_data' => 'percentage_daily_loss_safety_rules_money_management_7'],
                ],
                [
                    ['text' => '✏️ Custom', 'callback_data' => 'custom_percentage_daily_loss_safety_rules_money_management'],
                    ['text' => '🔙 Back', 'callback_data' => 'safety_rules_money_management'],
                ]
            ];

            if($user["money_management_status_daily_loss"] === "active"){
                $buttons[] = [
                    ['text' => '❌ Yes, Disable?', 'callback_data' => 'disable_enable_daily_loss_safety_rules_money_management']
                ];
            }else{
                $buttons[] = [
                    ['text' => '✅ Yes, Enable?', 'callback_data' => 'disable_enable_daily_loss_safety_rules_money_management'],
                ];
            }

            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🛑 Daily Loss Limit Configuration</b>

                Protect your account by setting a maximum daily loss.
                Trading will be automatically stopped if this limit is reached.

                <b>✅ Current:</b> {$user["money_management_daily_loss"]}%
                {$status}

                Set your daily loss limit:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }
        else if ($type == "custom_percentage_daily_loss_safety_rules_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✏️ Custom Daily Loss Limit</b>

                Enter maximum daily loss percentage (1-20%):
                <b>💡 Recommended:</b> 3-5% for protection
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '❌ Cancel', 'callback_data' => 'safety_rules_money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "enable_all_safety_rules_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ Enable All Safety Rules</b>

                This will activate:
                • Max Account Exposure: {$user->money_management_max_exposure}%
                • Max Concurrent Trades: {$user->money_management_trade_limit}
                • Daily Loss Limit: {$user->money_management_daily_loss}%

                These settings help protect your account from excessive risk.

                Confirm activation?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Activate All', 'callback_data' => 'activated_enable_all_safety_rules_money_management'],
                            ['text' => '❌ Cancel', 'callback_data' => 'safety_rules_money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "disable_all_safety_rules_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚠️ Disable All Safety Rules</b>

                <b>WARNING:</b> This will remove all protective limits on your trading.

                <b>You'll be able to:</b>
                • Risk unlimited account exposure
                • Open unlimited concurrent trades
                • No daily loss protection

                This is NOT recommended!

                Are you sure?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '⚠️ Yes, Disable', 'callback_data' => 'deactivated_disable_all_safety_rules_money_management'],
                            ['text' => '❌ Cancel', 'callback_data' => 'safety_rules_money_management'],
                        ]
                    ]
                ])
            ]);
        }
        // help 
        else if ($type == "help_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📖 Money Management Help</b>

                <b>🎯 What is Risk Per Trade?</b>
                The maximum amount you're willing to lose on a single trade, expressed as % of your account.

                <b>💡 Recommended Settings:</b>
                - Beginners: 0.5-1%
                - Intermediate: 1-2%
                - Advanced: 2-3%
                - Never exceed 5% per trade

                <b>⚠️ Understanding Cross Margin:</b>
                With cross margin, your actual risk can be higher than your margin. A -200% stop loss means you can lose 2x your margin!

                <b>🛡️ Safety Tips:</b>
                1. Start with small risk (1%)
                2. Never risk more than 20% total
                3. Use stop losses on ALL trades
                4. Monitor your exposure daily
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'money_management'],
                        ]
                    ]
                ])
            ]);
        }

        /* 
        ==========================
        CONFIG 
        ==========================
        */
        else if ($type == "config_uni_strategy_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => "Please select the exchange you're trading on:",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Bybit', 'callback_data' => "exchange_money_management_bybit"],
                            ['text' => 'Binance', 'callback_data' => "exchange_money_management_binance"],
                        ],
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "trades_mod_money_management") {
            // check money management type 
            if(!empty($user->money_management_type)){
                // $user->money_management_trades_mode = $user->money_management_type;
                // $user->save();

                $this->telegramMessageType("profit_stretegy_money_management", $chatId);
                return;
            }
            
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 You selected:</b> <code>{$user->money_management_exchange}</code>

                Is this a real trade you're taking or just for tracking/demo purposes.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💰 Real Trade', 'callback_data' => "trades_mod_money_management_select_real"],
                            ['text' => '🎮 Demo Only', 'callback_data' => "trades_mod_money_management_select_demo"],
                        ],
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management'],
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "profit_stretegy_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📝 Let's set up your {$user->money_management_trades_mode} trade tracking.</b>

                What's your strategy for taking profits?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🛠️ Manual', 'callback_data' => "manual_profit_stretegy_money_management"],
                            ['text' => '🎯 Close at TP', 'callback_data' => "close_tp_profit_stretegy_money_management"],
                        ],
                        [
                            ['text' => '💸 Partial', 'callback_data' => "partial_profit_stretegy_money_management"],
                            ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management']
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "close_tp_profit_stretegy_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📍 At which Take Profit level would you like to close your entire position?</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🎯 TP1', 'callback_data' => "close_tp_profit_stretegy_money_management_type_1"],
                            ['text' => '🎯 TP2', 'callback_data' => "close_tp_profit_stretegy_money_management_type_2"],
                        ],
                        [
                            ['text' => '🎯 TP3', 'callback_data' => "close_tp_profit_stretegy_money_management_type_3"],
                            ['text' => '🎯 TP4', 'callback_data' => "close_tp_profit_stretegy_money_management_type_4"],
                        ],
                        [
                            ['text' => '🎯 TP5', 'callback_data' => "close_tp_profit_stretegy_money_management_type_5"],
                            ['text' => '🎯 TP6', 'callback_data' => "close_tp_profit_stretegy_money_management_type_6"],
                        ],
                        [
                            ['text' => '🎯 TP7', 'callback_data' => "close_tp_profit_stretegy_money_management_type_7"],
                            ['text' => '🎯 TP8', 'callback_data' => "close_tp_profit_stretegy_money_management_type_8"],
                        ],
                        [
                            ['text' => '🎯 TP9', 'callback_data' => "close_tp_profit_stretegy_money_management_type_9"],
                            ['text' => '🎯 TP10', 'callback_data' => "close_tp_profit_stretegy_money_management_type_10"],
                        ]
                    ]

                ])
            ]);
        } 
        else if ($type == "partial_profit_stretegy_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ Select your partial profit strategy:</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📂 My Templates', 'callback_data' => "partial_profit_stretegy_money_management_templates"],
                            ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management']
                        ]
                    ]
                ])
            ]);
        }
        else if ($type == "partial_profit_stretegy_money_management_templates") {
            // Send "Connecting..." message
            $connectingMsg = $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            // reset SignalManager 
            $response = Http::withHeaders([
                'API-SECRET' => env("API_SECRET")
            ])->get(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/partial-templates', [
                "user_id" => $chatId
            ]);

            // Delete "Connecting..." 
            $this->safeTelegramCall('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);
            $templates = $response->json();

            // If No Active Trades
            if(count($templates) < 1){
                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📋 You don't have any templates.</b>

                    Create a template from SignalManager to get started.
                    EOT,
                    'parse_mode' => 'HTML'
                ]);
            }


            foreach ($templates as $key => $value) {

                $vals = [$value['tp1'], $value['tp2'], $value['tp3'], $value['tp4'], $value['tp5']];
                $payload = implode(',', $vals);
                $callbackData = "pp:$payload";

                Log::info($callbackData);

                $this->safeTelegramCall('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>{$value['name']}</b>

                    ✅ Partial profit strategy:
                    TP1: {$value['tp1']}%
                    TP2: {$value['tp2']}%
                    TP3: {$value['tp3']}%
                    TP4: {$value['tp4']}%
                    TP5: {$value['tp5']}%
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Select', 'callback_data' => $callbackData],
                            ]
                        ]
                    ])
                ]);
            }
        }

        // confimation 
        else if ($type == "confirm_stretegy_money_management") {
            $this->safeTelegramCall('sendMessage', [
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📝 Congratulations!</b>

                Universal Strategy Mode is now available for you to activate—enjoy the benefits of fully automated trading.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Back', 'callback_data' => 'uni_strategy_money_management']
                        ]
                    ]
                ])
            ]);
        }
    }
}
