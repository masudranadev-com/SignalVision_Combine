<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\PartialProfitTemplate;
use App\Models\TelegramUser;
use App\Models\SignalFormat;
use WeStacks\TeleBot\TeleBot;
use App\Models\ScheduleCrypto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use WeStacks\TeleBot\Objects\ReplyKeyboardMarkup;
use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Models\TelegraphBot;
use Telegram\Bot\Laravel\Facades\Telegram;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Jobs\WebhooksJob;
use GuzzleHttp\Client;
use \App\Http\Controllers\BybitAPIController;
use \App\Http\Controllers\BinanceAPIController;
use Storage;

class TelegramBotController extends Controller
{
    public $bybitAPI;
    public $binanceAPI;
    public $freeSignalLimit; 
    public function __construct()
    {
        $this->bybitAPI = new BybitAPIController();
        $this->binanceAPI = new BinanceAPIController();
        $this->freeSignalLimit = env("FREE_SIGNAL_LIMIT");
    }


    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function test()
    {
        // return $this->bybitAPI->getOrderOrPosition("6062724880");

        // $trade = ScheduleCrypto::find(1798);
        // $response = $this->bybitAPI->createPartialOrder($trade);

        // return $response;
    }
  
    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function telegram_webhook(Request $request)
    {
        $data = $request->all();

        $this->telegram_webhook_job($data);
        return;

        try {
            
            dispatch((new WebhooksJob($data))->onQueue('SignalManager_message'));
        } catch (\Throwable $th) {
            $chatId = $data['message']['chat']['id'];
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'We encountered a network error—please try again shortly. Code: 233',
            ]);
        }
    }
    public function telegram_webhook_job($data)
    {
        try {
            // ✅ Handle button click (callback_query)
            if (isset($data['callback_query'])) {
                $callbackData = $data['callback_query']['data'];
                $chatId = $data['callback_query']['message']['chat']['id'];
                $messageId = $data['callback_query']['message']['message_id'];
                $callbackId = $data['callback_query']['id'];
                $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
                
                // home  
                if($callbackData == "main_menu"){
                    $user->state = null; 
                    $this->telegramMessageType("main_menu", $chatId);
                }

                // help 
                else if($callbackData == "help"){
                    $user->state = null; 
                    $this->telegramMessageType("help", $chatId);
                }

                // history 
                else if($callbackData == "history"){
                    $user->state = null; 
                    $this->telegramMessageType("history", $chatId);
                }

                /*
                ===================
                MANULLY TRADE 
                ===================
                */
                else if(str_starts_with($callbackData, 'track_signal_manually_exchange_')){
                    $market = str_replace('track_signal_manually_exchange_', '', $callbackData);

                    // save data 
                    ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->delete();

                    $crypto =  new ScheduleCrypto();
                    $crypto->trade_id = now()->format('ymdihs');
                    $crypto->market = $market;
                    $crypto->chat_id = $chatId;
                    $crypto->status = "pending";
                    $crypto->save();

                    $this->telegramMessageType("track_signal_manually_pair", $chatId);
                }
                else if(str_starts_with($callbackData, 'track_signal_manually_take_profit_')){
                    $tp = str_replace('track_signal_manually_take_profit_', '', $callbackData);
                    $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();

                    // check every tp 
                    $prevTp = $tp-1;
                    if(empty($schedule["take_profit{$prevTp}"])){
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => "You can't set an empty target point. Your TP5 is currently empty. please enter a correct value to continue.",
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return;
                    }

                    if($tp > 10){
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            Unfortunately, you cannot add any additional target profit at this point.
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return;
                    }

                    $this->telegramMessageType("track_signal_manually_take_profit", $chatId, ["tp" => $tp]);
                }
                else if($callbackData ==='track_signal_manually_continue_to_stop_loss'){
                    // save data 
                    $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();

                    // random 
                    $price = $schedule->entry_target;
                    $abs = abs($price);
                    if ($abs == 0) {
                        return [0];
                    }
                    // Calculate step based on scale
                    if ($abs < 1) {
                        $step = pow(10, floor(log10($abs)) - 1);
                    } else {
                        $step = pow(10, floor(log10($abs)) - 1);
                    }
                    // prices
                    $prices = "";
                    for ($i = 0; $i < 3; $i++) {
                        $offset = rand(-2, 2);
                        $newPrice = $price + ($offset * $step);
                        $newPrice = max($newPrice, 0);
                        $prices .= round($newPrice, 10).", ";
                    }

                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>🛡️ Stop Loss Setup</b>

                        What's your stop loss price?

                        💡 Example: {$prices}
                        Please enter stop loss:
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '↩️ Back', 'callback_data' => "manually_tracking_back_track_signal_manually_leverage"]
                                ]
                            ]
                        ])
                    ]);

                    $user->state = "track_signal_manually_stop_loss";
                    $user->save();

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }
                // back 
                else if(str_starts_with($callbackData, 'manually_tracking_back_')){
                    $step = str_replace('manually_tracking_back_', '', $callbackData);

                    if(str_starts_with($step, "track_signal_manually_take_profit_")){
                        $tp = str_replace('track_signal_manually_take_profit_', '', $step);
                        $this->telegramMessageType("track_signal_manually_take_profit", $chatId, ["tp" => $tp]);
                    }else{
                        $this->telegramMessageType($step, $chatId);
                    }
                }
                // market entry   
                else if(str_starts_with($callbackData, 'manually_tracking_market_entry_')){
                    $id = str_replace('manually_tracking_market_entry_', '', $callbackData);
                    $schedule =  ScheduleCrypto::find($id);

                    // market entry 
                    $cryptoPrices = cryptoPrices();
                    $currentPrice = $cryptoPrices[$schedule->instruments] ?? 0;

                    $schedule->last_alert = "Market Entry";
                    $schedule->save();

                    // leverage 
                    $this->telegramMessageType("track_signal_manually_leverage", $chatId);
                }

                /*
                ===================
                NEW TRADE OPEN
                ===================
                */
                // new signal  
                else if($callbackData == "try_another_signal"){
                    $this->telegramMessageType("track_signal", $chatId);
                    $user->state = "track_new_signal";
                }
                // select exchange  
                else if(str_starts_with($callbackData, 'exchange_')){
                    $marketID = str_replace('exchange_', '', $callbackData);
                    $explode = explode("_", $marketID);
                    $market = $explode[0];

                    $text = Cache::get("new_trade_format_{$user->chat_id}");
                    $this->validateNewTrade($text, $market, $chatId);
                }
                // trade_type 
                else if(str_starts_with($callbackData, 'trade_type_')){
                    $typeID = str_replace('trade_type_', '', $callbackData);
                    $explode = explode("_", $typeID);
                    $type = $explode[0];
                    $id = $explode[1];

                    $schedule = ScheduleCrypto::find($id);
                    $schedule->type = $type; 
                    $schedule->save();

                    $this->telegramMessageType("trade_type", $chatId, ["id" => $id]);
                }
                // signal auto input 
                else if($callbackData == "signal_auto_input"){
                    $msg = Cache::get('unrecognized_track_signal_message_'.$user->id);

                    // $output = $this->deepSeek_SignalFormat($msg);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "It will be coming up shortly.",
                    ]);
                }
                // manual 
                // else if(str_starts_with($callbackData, 'signal_manually_input_')){
                //     $id = str_replace('signal_manually_input_', '', $callbackData);
                //     $user->state = null;

                //     $this->telegramMessageType("signal_manually_input", $chatId, ["id" => $id]);
                // }
                else if(str_starts_with($callbackData, 'select_trade_mod')){ 
                    $id = str_replace('select_trade_mod_', '', $callbackData);

                    $this->telegramMessageType("select_trade_mod", $chatId, ["id" => $id]);
                }

                /*
                ===================
                EDIT TRADE 
                ===================
                */
                else if(str_starts_with($callbackData, 'edit_signal_')){
                    $id = str_replace('edit_signal_', '', $callbackData);

                    $this->telegramMessageType("edit_signal_buttons", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'entry_type_edit_signal_')){
                    $id = str_replace('entry_type_edit_signal_', '', $callbackData);

                    $this->telegramMessageType("edit_signal_entry_type", $chatId, ["id" => $id]);
                }


                /*
                ===================
                partial profits  
                ===================
                */
                else if(str_starts_with($callbackData, 'partial_profit_select_templates')){
                    $id = str_replace('partial_profit_select_templates_', '', $callbackData);

                    $schedule = ScheduleCrypto::find($id);
                    $schedule->profit_strategy = "partial_profits";
                    $schedule->save();
                    $this->telegramMessageType("partial_profit_select_templates", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'partial_profit_my_templates_')){
                    $id = str_replace('partial_profit_my_templates_', '', $callbackData);
                    
                    $this->telegramMessageType("partial_profit_my_templates", $chatId, ["id" => $id]);
                } 
                else if(str_starts_with($callbackData, 'partial_profit_templates_select_')){
                    $valueIds = str_replace('partial_profit_templates_select_', '', $callbackData);
                    $valueIdsExplode = explode("_", $valueIds);
                    $temp_id = $valueIdsExplode[0];
                    $sche_id = $valueIdsExplode[1];

                    $template = PartialProfitTemplate::find($temp_id);

                    $schedule = ScheduleCrypto::find($sche_id);
                    $schedule->partial_profits_tp1 = $template->tp1;
                    $schedule->partial_profits_tp2 = $template->tp2;
                    $schedule->partial_profits_tp3 = $template->tp3;
                    $schedule->partial_profits_tp4 = $template->tp4;
                    $schedule->partial_profits_tp5 = $template->tp5;
                    $schedule->save();

                    if($schedule->status === "pending"){
                        $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $schedule->id]);
                    }else{
                        Telegram::answerCallbackQuery([
                            'callback_query_id' => $callbackId,
                            'text' => '✅ Partial profit updated successfully!',
                            'show_alert' => false,
                        ]);

                        // remove old message 
                        $allTelegramMsgIds = Cache::get("my_signal_update_partial_profit_message_ids_$user->id");
                        if (!empty($allTelegramMsgIds)) {
                            foreach ($allTelegramMsgIds as $messageId) {
                                
                                try {
                                    Telegram::deleteMessage([
                                        'chat_id' => $chatId,
                                        'message_id' => $messageId,
                                    ]);
                                } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                                    Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: 5003");
                                }
                            }
                        }
                        Cache::forget("my_signal_update_partial_profit_message_ids_$user->id");
                    }
                }
                else if(str_starts_with($callbackData, 'partial_profit_skip_template_save_')){
                    $id = str_replace('partial_profit_skip_template_save_', '', $callbackData);
                    $schedule = ScheduleCrypto::find($id);

                    // remove old message 
                    $allTelegramMsgIds = Cache::get("my_signal_update_partial_profit_message_ids_$user->id");
                    if (!empty($allTelegramMsgIds)) {
                        foreach ($allTelegramMsgIds as $messageId) {
                            try {
                                Telegram::deleteMessage([
                                    'chat_id' => $chatId,
                                    'message_id' => $messageId,
                                ]);
                            } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                                Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: SKIP");
                            }
                        }
                    }
                    Cache::forget("my_signal_update_partial_profit_message_ids_$user->id");
                }
                else if(str_starts_with($callbackData, 'partial_profit_templates_delete_')){
                    $id = str_replace('partial_profit_templates_delete_', '', $callbackData);
                    PartialProfitTemplate::where("id", $id)->where("user_id", $chatId)->delete();

                    // Delete the message
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    // Send a "toast" message to the user
                    Telegram::answerCallbackQuery([
                        'callback_query_id' => $callbackId, // from the update
                        'text' => '✅ Template deleted successfully!',
                        'show_alert' => false, // false = small toast at bottom, true = popup
                    ]);

                }
                else if(str_starts_with($callbackData, 'partial_profit_new_templates')){
                    $id = str_replace('partial_profit_new_templates_', '', $callbackData);

                    $schedule = ScheduleCrypto::find($id);
                    $schedule->profit_strategy = "partial_profits";
                    $schedule->partial_profits_tp1 = null;
                    $schedule->partial_profits_tp2 = null;
                    $schedule->partial_profits_tp3 = null;
                    $schedule->partial_profits_tp4 = null;
                    $schedule->partial_profits_tp5 = null;
                    $schedule->save();

                    $user->state = "take_partial_profits_$id";
                    $this->telegramMessageType("partial_profit_new_templates", $chatId, ["tp" => 1, "id" => $id, "percentage" => 100]);
                }
                else if(str_starts_with($callbackData, 'edit_partial_profit_strategy')){
                    $id = str_replace('edit_partial_profit_strategy_', '', $callbackData);

                    $schedule = ScheduleCrypto::find($id);
                    $schedule->partial_profits_tp1 = null;
                    $schedule->partial_profits_tp2 = null;
                    $schedule->partial_profits_tp3 = null;
                    $schedule->partial_profits_tp4 = null;
                    $schedule->partial_profits_tp5 = null;
                    $schedule->save();
                    
                    $user->state = "take_partial_profits_$id";
                    $this->telegramMessageType("partial_profit_new_templates", $chatId, ["tp" => 1, "id" => $id, "percentage" => 100]);
                }
                else if(str_starts_with($callbackData, 'take_partial_profits_')){
                    $valueIds = str_replace('take_partial_profits_', '', $callbackData);
                    $valueIdsExplode = explode("_", $valueIds);
                    $value = $valueIdsExplode[0];
                    $id = $valueIdsExplode[1];

                    $this->takePartialProfits($chatId, $value, $id);
                }
                else if(str_starts_with($callbackData, 'confirm_partial_profit_strategy_')){
                    $id = str_replace('confirm_partial_profit_strategy_', '', $callbackData);
                    $schedule = ScheduleCrypto::find($id);

                    // $user->state = "trade_volume_question_amount_usdt_{$schedule->id}";
                    // $this->telegramMessageType("trade_volume_question_amount_usdt", $chatId, ["id" => $schedule->id]);
                    $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $schedule->id]);
                }
                else if(str_starts_with($callbackData, 'partial_profit_save_template_name_')){
                    $id = str_replace('partial_profit_save_template_name_', '', $callbackData);
                    $schedule = ScheduleCrypto::find($id);

                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>Please name this profit strategy:</b>
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    $user->state = "save_template_name_$id";

                    // messages 
                    if($schedule->status == "pending"){
                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $msgresInfo->message_id;
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    }else{
                        // add new ids  
                        $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $msgresInfo->message_id;
                        Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
                    }
                }
                // Trade Volume Question  
                else if(str_starts_with($callbackData, 'btn_trade_volume_question_amount_')){
                    $id = str_replace('btn_trade_volume_question_amount_', '', $callbackData);
                    
                    $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'reduce_trade_volume_question_amount_')){
                    $id = str_replace('reduce_trade_volume_question_amount_', '', $callbackData);
                    
                    $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $id, "reduce" => true]);
                }
                else if(str_starts_with($callbackData, 'trade_volume_question_amount_usdt_')){
                    $id = str_replace('trade_volume_question_amount_usdt_', '', $callbackData);
                    
                    $this->telegramMessageType("trade_volume_question_amount_usdt", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'trade_volume_question_amount_coin_')){
                    $id = str_replace('trade_volume_question_amount_coin_', '', $callbackData);

                    $this->telegramMessageType("trade_volume_question_amount_coin", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'continue_demo_mode_')){
                    $id = str_replace('continue_demo_mode_', '', $callbackData);
                    $schedule = ScheduleCrypto::find($id);
                    $schedule->type = "demo";
                    $schedule->save();

                    
                    // TradeText  
                    $tradeText = "✅ Congratulations! Your trade has been successfully updated.";
                    if($schedule->status === "pending"){
                        $tradeText = "✅ Congratulations! Your trade is now being tracked.";
                    }

                    // send 
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $tradeText,
                        'reply_markup' => json_encode([
                            'keyboard' => [
                                ['➕ Track Signal', '📋 My Signals'],
                                ['📊 History', '🔑 License'],
                                ['🆘 Help']
                            ],
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true
                        ])
                    ]);

                    // remove old message 
                    $allTrackSignalMsgs = Cache::get('track_signal_message_ids_'.$user->id);
                    if (!empty($allTrackSignalMsgs)) {
                        foreach ($allTrackSignalMsgs as $messageId) {
                            try {
                                Telegram::deleteMessage([
                                    'chat_id' => $chatId,
                                    'message_id' => $messageId,
                                ]);
                            } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                                Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: 5002");
                            }
                        }
                    }
                    // Forget the cached message IDs after deleting
                    Cache::forget('track_signal_message_ids_'.$user->id);
                }
                // strat tracking 
                else if(str_starts_with($callbackData, 'start_tracking_')){
                    $id = str_replace('start_tracking_', '', $callbackData);

                    $this->startTracking($id);
                }
                // menual
                else if(str_starts_with($callbackData, 'manual_management_')){
                    $id = str_replace('manual_management_', '', $callbackData);
                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $schedule->profit_strategy = "manual_management";
                    $schedule->save();

                    if($schedule->status == "pending"){
                        // $this->telegramMessageType("trade_volume_question_amount_usdt", $chatId, ["id" => $id]);
                        $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $schedule->id]);
                    }else{
                        // real trade 
                        if($schedule->type === "real"){
                            // connecting ...
                            $connectingMsg = Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => 'Connecting...',
                            ]);

                            // Closed and Create order
                            if($schedule->market === "bybit"){
                                $takeProfit = $this->bybitAPI->takeProfit($schedule);
                            }

                            // Delete "Connecting..." 
                            Telegram::deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $connectingMsg->getMessageId(),
                            ]);
                        }

                        Telegram::editMessageText([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'text' => "✅ Your strategy has been successfully changed.",
                            'parse_mode' => 'HTML',
                        ]);
                    }
                }
                // specific tp  
                else if(str_starts_with($callbackData, 'close_specific_tp_template_')){
                    $id = str_replace('close_specific_tp_template_', '', $callbackData);
                    
                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $schedule->profit_strategy = "close_specific_tp";
                    $schedule->save();

                    $this->telegramMessageType("close_specific_tp", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'close_specific_tp_type_')){
                    $replaceText = str_replace('close_specific_tp_type_', '', $callbackData);
                    $explode = explode("_", $replaceText);
                    $id = $explode[0];
                    $tp = $explode[1];

                    $schedule = ScheduleCrypto::where("chat_id", $chatId)->where("id", $id)->first();
                    
                    if($schedule->status == "pending"){
                        $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $schedule->id]);
                    }else{
                        // real trade 
                        if($schedule->type === "real"){
                            // connecting ...
                            $connectingMsg = Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => 'Connecting...',
                            ]);

                            // Closed and Create order
                            if($schedule->market === "bybit"){
                                $tpsl = $this->bybitAPI->updateTPSL($schedule, $tp, null);
                                if(!$tpsl["status"]){
                                    Telegram::sendMessage([
                                        'chat_id' => $chatId,
                                        'text' => <<<EOT
                                        <b>{$tpsl["msg"]}</b>
                                        EOT,
                                        'parse_mode' => 'HTML',
                                    ]);

                                    // Delete "Connecting..." 
                                    Telegram::deleteMessage([
                                        'chat_id' => $chatId,
                                        'message_id' => $connectingMsg->getMessageId(),
                                    ]);
                                    return;
                                }
                            }

                            // Delete "Connecting..." 
                            Telegram::deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $connectingMsg->getMessageId(),
                            ]);
                        }

                        Telegram::editMessageText([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'text' => "✅ Your strategy has been successfully changed.",
                            'parse_mode' => 'HTML',
                        ]);
                    }

                    $schedule->specific_tp = $tp;
                    $schedule->save();
                }
                /*
                =================
                TRDAE NOTIFICATION
                =================
                */
                else if(str_starts_with($callbackData, 'trade_report_loss_')){
                    $id = str_replace('trade_report_loss_', '', $callbackData);
                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $user->state = "trade_report_loss_$id";
                    $user->save();

                    $this->telegramMessageType("trade_report", $chatId);
                }

                /*
                =================
                My Trade Section 
                =================
                */
                // update_trade
                else if(str_starts_with($callbackData, 'update_trade_buttons_')){
                    $scheduleId = str_replace('update_trade_buttons_', '', $callbackData);
                    
                    $this->telegramMessageType("update_trade_buttons", $chatId, ["id" => $scheduleId]);
                }
                // update_trade
                else if($callbackData == 'update_trade_cancel'){
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                }
                // close trade 
                else if(str_starts_with($callbackData, 'close_trade_')){
                    $scheduleId = str_replace('close_trade_', '', $callbackData);
                    $tradeUpdated = ScheduleCrypto::where('id', $scheduleId)->first();

                    if(!empty($tradeUpdated)){
                        $this->telegramMessageType("close_trade", $chatId, ["id" => $scheduleId]);
                    }else{
                        Telegram::editMessageText([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'text' => "⚠️ This trade was already closed.",
                            'parse_mode' => 'HTML',
                        ]);

                        removeTradeFromCache($scheduleId);
                    }
                    
                }
                // yes close trade
                else if(str_starts_with($callbackData, 'yes_close_trade_')){
                    $scheduleId = str_replace('yes_close_trade_', '', $callbackData);
                    $tradeUpdated = ScheduleCrypto::where('id', $scheduleId)->first();

                    if(!empty($tradeUpdated)){
                        // if real trade 
                        if($tradeUpdated->type === "real"){
                            // Send "Connecting..." message
                            $connectingMsg = Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => 'Connecting...',
                            ]);

                            // Closed order
                            if($tradeUpdated->market === "bybit"){
                                $closedOrder = $this->bybitAPI->closeOrder($tradeUpdated);
                            }else{
                                $closedOrder = $this->binanceAPI->closeOrder($tradeUpdated);
                            }

                            // Delete "Connecting..." 
                            Telegram::deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $connectingMsg->getMessageId(),
                            ]);

                            // false 
                            if(!$closedOrder["status"]){
                                Telegram::sendMessage([
                                    'chat_id' => $chatId,
                                    'message_id' => $messageId,
                                    'text' => $closedOrder["msg"],
                                    'reply_markup' => json_encode([
                                        'keyboard' => [
                                            ['➕ Track Signal', '📋 My Signals'],
                                            ['📊 History', '🔑 License'],
                                            ['🆘 Help']
                                        ],
                                        'resize_keyboard' => true,
                                        'one_time_keyboard' => true
                                    ])
                                ]);

                                return;
                            }
                        }

                        if($tradeUpdated->status === "waiting"){
                            $tradeUpdated->delete();
                            $text = "✅ Trade cancel Successfully!";
                        }else{
                            $instrument = $tradeUpdated->instruments;
                            $cryptoPrices = cryptoPrices();
                            $currentPrice = $cryptoPrices[$instrument] ?? 0;
                            if(empty($currentPrice)){
                                $tradeUpdated->delete();
                                
                                // remove from cache 
                                removeTradeFromCache($scheduleId);

                                Telegram::editMessageText([
                                    'chat_id' => $chatId,
                                    'message_id' => $messageId,
                                    'text' => "⚠️ Current price not found! Trade has been removed!",
                                    'parse_mode' => 'HTML',
                                ]);
                                return;
                            }

                            $tradeUpdated->status = "closed";
                            $tradeUpdated->save();

                            $text = "✅ Trade Closed Successfully!";
                        }
                        Telegram::editMessageText([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'text' => $text,
                            'parse_mode' => 'HTML',
                        ]);
                    }else{
                        Telegram::editMessageText([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'text' => "⚠️ This trade was already closed.",
                            'parse_mode' => 'HTML',
                        ]);
                    }

                    // remove from cache 
                    removeTradeFromCache($scheduleId);
                }
                // cancel close trade
                else if($callbackData === 'cancel_close_trade'){
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                }
                // stop loss 
                else if(str_starts_with($callbackData, 'update_trade_stop_loss_')){
                    $id = str_replace('update_trade_stop_loss_', '', $callbackData);
                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $user->save();

                    $this->telegramMessageType("update_trade_loss", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'by_update_trade_loss_')){
                    $baseID = str_replace('by_update_trade_loss_', '', $callbackData);
                    $explode = explode("_", $baseID);
                    $base = $explode[0];
                    $id = $explode[1];

                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $user->state = "update_trade_stop_loss_{$base}_{$id}";
                    $user->save();

                    if($base === "price"){
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>💵 Stop Loss by Price</b>

                            <b>Enter your stop loss price:</b>
                            <b>Current:</b> {$schedule->stop_loss}
                            EOT,
                            'parse_mode' => 'HTML'
                        ]);
                    }else{
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>📊 Stop Loss by Percentage</b>

                            Enter percentage from entry:
                            💡 Example: 5 (for 5% stop loss)
                            ⚠️ Negative values auto-calculated
                            EOT,
                            'parse_mode' => 'HTML'
                        ]);
                    }
                    
                }
                // entry point  
                else if(str_starts_with($callbackData, 'update_trade_entry_point_')){
                    $id = str_replace('update_trade_entry_point_', '', $callbackData);
                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $user->state = "update_trade_entry_point_$id";
                    $user->save();


                    $this->telegramMessageType("update_trade_entry_point", $chatId, ["id" => $id]);
                }
                // update partial profit  
                else if(str_starts_with($callbackData, 'update_trade_partial_profit_')){
                    $id = str_replace('update_trade_partial_profit_', '', $callbackData);
                    $schedule = ScheduleCrypto::find($id);

                    $schedule = ScheduleCrypto::find($id);
                    for ($i = $schedule->height_tp + 1; $i <= 5; $i++) {
                        $field = "partial_profits_tp{$i}";
                        $schedule->$field = null;
                    }
                    $schedule->save();

                    $user->state = "update_trade_partial_profit_$id";
                    $this->telegramMessageType("update_trade_partial_profit", $chatId, ["id" => $id, "tp" => $schedule->height_tp + 1]);
                }
                else if(str_starts_with($callbackData, 'update_partial_profits_percentage_')){
                    $data = str_replace('update_partial_profits_percentage_', '', $callbackData);
                    $dataExplode = explode("_", $data);
                    $id = $dataExplode[0];
                    $percentage = $dataExplode[1];
                    $schedule = ScheduleCrypto::find($id);


                    $this->updatePartialProfits($chatId, $percentage, $id);
                }
                // update leverage 
                else if(str_starts_with($callbackData, 'update_trade_leverage_')){
                    $id = str_replace('update_trade_leverage_', '', $callbackData);
                    // $user->state = "update_trade_leverage_$id";
                    $schedule = ScheduleCrypto::find($id);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>⚖️ Leverage Adjustment</b>

                        <b>Current:</b> {$schedule->leverage}X
        
                        - Select new leverage:
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => [
                            [
                                ['text' => '5X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_5"],
                                ['text' => '10X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_10"],
                                ['text' => '20X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_20"],
                            ],
                            [
                                ['text' => '25X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_25"],
                                ['text' => '50X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_50"],
                                ['text' => '75X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_75"],
                            ],
                            [
                                ['text' => '100X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_100"],
                                ['text' => '125X', 'callback_data' => "btn_update_trade_leverage_{$schedule->id}_125"],
                                ['text' => 'Custom', 'callback_data' => "custom_update_trade_leverage_{$schedule->id}"]
                            ]
                        ]])
                    ]);
                }
                else if(str_starts_with($callbackData, 'custom_update_trade_leverage_')){
                    $id = str_replace('custom_update_trade_leverage_', '', $callbackData);
                    $user->state = "update_trade_leverage_$id";
                    $schedule = ScheduleCrypto::find($id);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>✏️ Custom Leverage</b>

                        Enter your desired leverage (1-125):
                        💡 Example: 15
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);
                }
                else if(str_starts_with($callbackData, 'btn_update_trade_leverage_')){
                    $levID = str_replace('btn_update_trade_leverage_', '', $callbackData);
                    $explode = explode("_", $levID);
                    $id = $explode[0];
                    $leverage = $explode[1];

                    $schedule = ScheduleCrypto::find($id);
                    $this->telegramMessageType("update_trade_leverage", $chatId, ["id" => $id, "leverage" => $leverage]);
                }

                // trailing_stop 
                else if(str_starts_with($callbackData, 'update_trade_trailing_stop_')){
                    $id = str_replace('update_trade_trailing_stop_', '', $callbackData);

                    $schedule = ScheduleCrypto::latest()
                    ->where("chat_id", $chatId)
                    ->where("id", $id)
                    ->first();

                    $instrument = $schedule->instruments;
                    $cryptoPrices = cryptoPrices();
                    $currentPrice = $cryptoPrices[$instrument] ?? 'Waiting';

                    $trailingStopTxt = "";
                    if(!empty($schedule->stop_loss_percentage) || !empty($schedule->stop_loss_price)){
                        if(!empty($schedule->stop_loss_percentage)){
                            $trailingMethod = "Percentage-Based";
                            $trailingDistance = $schedule->stop_loss_percentage."%";
                        }else{
                            $trailingMethod = "Price-Based";
                            $trailingDistance = $schedule->stop_loss_price."$";
                        }

                        $trailingStopTxt = "\n📉 Trailing: <code>{$trailingMethod}, {$trailingDistance}</code>";
                    }

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📉 Trailing Stop Configuration</b>

                        Trailing stops automatically adjust your stop loss as the price moves in your favor, locking in profits while giving your trade room to breathe.

                        <b>Current Trade: <code>{$instrument} {$schedule->tp_mode}</code></b>
                        <b>Entry Price: <code>{$schedule->entry_target}</code></b>
                        <b>Current Price: <code>{$currentPrice}</code></b>
                        <b>Current Stop Loss: <code>{$schedule->stop_loss}</code></b>{$trailingStopTxt}
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => [
                            [
                                ['text' => '🎯 Percentage Based', 'callback_data' => "trade_trailing_stop_percentage_based-{$schedule->id}"],
                                ['text' => '💵 Price Based', 'callback_data' => "trade_trailing_stop_price_based-{$schedule->id}"]
                            ],
                            [
                                ['text' => '❌ Cancelled', 'callback_data' => "trade_trailing_cancelled"]
                            ]
                        ]])
                    ]);
                }
                else if(str_starts_with($callbackData, 'trade_trailing_stop_')){
                    $idType = str_replace('trade_trailing_stop_', '', $callbackData);
                    $explode = explode("-", $idType);
                    $type = $explode[0];
                    $id = $explode[1];

                    $this->telegramMessageType("update_trade_trailing_stop", $chatId, ["type" => $type, "id" => $id]);
                }
                else if(str_starts_with($callbackData, 'trailing_stop_input_')){
                    $idTypeValue = str_replace('trailing_stop_input_', '', $callbackData);
                    $explode = explode("-", $idTypeValue);

                    $type = $explode[0];
                    $id = $explode[1];
                    $value = $explode[2];

                    if($value === "custom"){
                        $this->telegramMessageType("trailing_stop_input_custom", $chatId, ["type" => $type, "id" => $id]);
                    }else{
                        $this->telegramMessageType("trailing_stop_input", $chatId, ["type" => $type, "id" => $id, "value" => $value]);
                    }
                }
                else if($callbackData === 'trade_trailing_cancelled'){
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                }
                // market entry
                else if(str_starts_with($callbackData, 'update_trade_market_entry_')){
                    $id = str_replace('update_trade_market_entry_', '', $callbackData);
                    $trade = ScheduleCrypto::find($id);
                    if(empty($trade)){
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>❌ Has this trade already been removed?</b>
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        return;
                    }
                    $this->telegramMessageType("update_trade_market_entry", $chatId, ["id" => $id]);
                }
                else if(str_starts_with($callbackData, 'confirm_update_trade_market_entry_')){
                    $id = str_replace('confirm_update_trade_market_entry_', '', $callbackData);
                    $trade = ScheduleCrypto::find($id);
                    $priceResponse = Http::get("https://thechainguard.com/instruments-price", [
                        "symbol" => $trade->instruments,
                        "market" => $trade->market
                    ]);
                    $price = $priceResponse->json();

                    if($trade->status === "pending"){
                        $trade->entry_target = $price;
                        $trade->last_alert = "Market Entry";
                        $trade->save();

                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>✅ Congratulations! Entry price successfully updated.</b>
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        $this->telegramMessageType("select_trade_mod", $chatId, ["id" => $id]);
                    }else{
                        if(empty($price)){
                            Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => <<<EOT
                                <b>❌ Kindly wait a moment, please.?</b>
                                EOT,
                                'parse_mode' => 'HTML',
                            ]);

                            return;
                        }

                        // real trade 
                        if($trade->type === "real"){
                            // connecting ...
                            $connectingMsg = Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => 'Connecting...',
                            ]);

                            //  Market order
                            if($trade->market === "bybit"){
                                // $closedOrder = $this->bybitAPI->closeOrder($trade);
                                $orderPlace = $this->bybitAPI->marketEntry($trade);
                            }else{
                                // $closedOrder = $this->binanceAPI->closeOrder($trade);
                                $orderPlace = $this->binanceAPI->createOrder($trade, "market");
                            }

                            // Delete "Connecting..." 
                            Telegram::deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $connectingMsg->getMessageId(),
                            ]);

                            // false 
                            if(!$orderPlace["status"]){
                                Telegram::sendMessage([
                                    'chat_id' => $chatId,
                                    'message_id' => $messageId,
                                    'text' => $orderPlace["msg"],
                                ]);
                                return;
                            }

                            // Closed and Create order
                            if($trade->market === "bybit"){
                                $closedOrder = $this->bybitAPI->closeOrder($trade);
                            }else{
                                $closedOrder = $this->binanceAPI->closeOrder($trade);
                            }

                            // false 
                            if(!$closedOrder["status"]){
                                Telegram::sendMessage([
                                    'chat_id' => $chatId,
                                    'message_id' => $messageId,
                                    'text' => $closedOrder["msg"],
                                ]);
                            }

                        }else{
                            startWaitingTrade($trade, $price);
                        }

                        $trade->entry_target = $price;
                        $trade->status = "running";
                        $trade->save();

                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>✅ Congratulations! Market Entry successfully updated.</b>
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);
                    }
                }

                // back 
                else if(str_starts_with($callbackData, 'update_trade_back_')){
                    $id = str_replace('update_trade_back_', '', $callbackData);
                    $trade = ScheduleCrypto::find($id);
                    if(empty($trade)){
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>❌ Has this trade already been removed?</b>
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        return;
                    }

                    // condition 
                    if($trade->status === "pending"){
                        $this->telegramMessageType("edit_signal_buttons", $chatId, ["id" => $id]);
                    }else{
                        $this->telegramMessageType("update_trade_buttons", $chatId, ["id" => $id]);
                    }
                }

                /*
                =================
                Trade History   
                =================
                */ 
                else if($callbackData === 'go_to_list'){
                    $this->telegramMessageType("my_signals", $chatId, ["msg_id" => null]);
                }
                else if(str_starts_with($callbackData, 'history_trades_type')){
                    $type = str_replace('history_trades_type_', '', $callbackData);
                    $this->telegramMessageType("history_trades_type", $chatId, ["type" => $type]);
                }
                else if(str_starts_with($callbackData, 'statistics_trades_type_')){
                    $data = str_replace('statistics_trades_type_', '', $callbackData);
                    $explode = explode("_", $data);
                    $date = $explode[0];

                    $this->telegramMessageType("statistics", $chatId, ["date" => $date]);
                }
                else if($callbackData === "statistics_trades_change_time"){
                    $this->telegramMessageType("statistics_trades_change_time", $chatId);
                } 
                else if(str_starts_with($callbackData, 'history_trades_quick_list_')){
                    $type = str_replace('history_trades_quick_list_', '', $callbackData);
                    $this->telegramMessageType("history_trades_quick_list", $chatId, ["type" => $type]);
                }
                // provider_statistics 
                else if($callbackData === "provider_statistics"){
                    $this->telegramMessageType("provider_statistics", $chatId);
                }
                else if(str_starts_with($callbackData, 'delete_trade_histories')){
                    $type = str_replace('delete_trade_histories_', '', $callbackData);
                    $ucType = ucfirst($type);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>🗑️ Are you sure?</b>

                        It will delete all of your {$ucType} histories.
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => "📊 History", 'callback_data' => "history"],
                                    ['text' => "❌ Yes Delete", 'callback_data' => "yes_delete_trade_histories_$type"],
                                ],
                            ]
                        ])
                    ]);
                }
                else if(str_starts_with($callbackData, 'yes_delete_trade_histories')){
                    $type = str_replace('yes_delete_trade_histories_', '', $callbackData);
                    $ucType = ucfirst($type);

                    ScheduleCrypto::where('chat_id', $chatId)
                    ->where('type', $type)
                    ->where('status', 'closed')
                    ->update([
                        "status" => "delete"
                    ]);

                    Telegram::editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => "⚠️ {$ucType} trade histories have been successfully deleted.",
                        'parse_mode' => 'HTML',
                    ]);
                }

                // get prmiam  
                else if($callbackData === "get_premium_license"){
                    $user->state = "license";

                    $response = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        🔑 <b>Enter your license key:</b>
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);
                }

                $user->save();
                return response('ok');
            }

            if (!isset($data['message'])) {
                return response('ok');
            }

            $chatId = $data['message']['chat']['id'];
            $text = trim($data['message']['text'] ?? '');
            $messageId = $data['message']['message_id'];
            $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);

            // start
            if ($text === '/start' || $text === '🏠 Main Menu') {
                $user->state = null;
                $user->save();
                $this->telegramMessageType("main_menu", $chatId);
                return;
            }

            // track signal 
            if ($text === '➕ Track Signal') {
                $user->state = null;
                $user->save();

                $this->telegramMessageType("track_signal", $chatId);
                return;
            }

            // history  
            if ($text === '📊 History') {
                $user->state = null;
                $user->save();

                $this->telegramMessageType("history", $chatId);
                return;
            }

            // help 
            elseif ($text === '/help' || $text === '🆘 Help') {
                $user->state = null;
                $user->save();
                $this->telegramMessageType("help", $chatId);
                return;
            }

            // list
            elseif ($text === '/list' || $text === '📋 My Signals' || $text === "🔄 Refresh") {
                $user->state = null;
                $user->save();
                $this->telegramMessageType("my_signals", $chatId, ["msg_id" => $messageId]);
                return;
            }

            // list
            elseif ($text === '/license' || $text === '🔑 License') {
                $user->state = null;
                $user->save();
                $this->telegramMessageType("license", $chatId);
                return;
            }
            
            // cancel
            elseif ($text === '/cancel') {
                $schedules = ScheduleCrypto::latest()
                ->where("chat_id", $chatId)
                ->where("status", "running")
                ->get();
            
                $messages = [];
                foreach ($schedules as $value) {
                    $messages[] = [
                        'text' => <<<EOT
                        <b>📊 Coin:</b> <code>{$value->instruments}</code>
                        <b>📈 Type:</b> {$value->tp_mode}
                        <b>🎯 Entry:</b> <code>{$value->entry_target}</code>
                        <b>🛑 SL:</b> <code>{$value->stop_loss}</code>
                        EOT,
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '❌ Cancel Trade', 'callback_data' => "cancel_trade_{$value->id}"]
                                ]
                            ]
                        ])
                    ];
                }
                
                foreach ($messages as $message) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $message['text'],
                        'parse_mode' => 'HTML',
                        'reply_markup' => $message['reply_markup'],
                    ]);
                }
                
                $user->state = null;
                $user->save();
                return;
            }
            
            // else  
            else {
                // new signal 
                if(empty($user->state)){
                    Cache::forever("new_trade_format_{$user->chat_id}", $data);

                    // get uni leverage 
                    $strategy = Http::withHeaders([
                        'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
                    ])->get(config('services.api.ctypto_end_point').'/api/money-management/uni-strategy', [
                        "chat_id" => $chatId
                    ]);
                    $strategyInfo = $strategy->json();
                    if($strategyInfo["strategy_status"] !== "inactive"){
                        Cache::forever("uni_strategy_{$user->chat_id}", $strategyInfo);
                        $this->validateNewTrade($data, $strategyInfo["strategy_exchange"], $chatId, $strategyInfo);
                        return;
                    }

                    // treding market 
                    $messageResponseTredingMarkets = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please select the exchange you're trading on:",
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Bybit', 'callback_data' => "exchange_bybit"],
                                    ['text' => 'Binance', 'callback_data' => "exchange_binance"],
                                ]
                            ]
                        ])
                    ]);
                }
                // partial profits 
                elseif(str_starts_with($user->state, 'take_partial_profits_')){
                    $id = str_replace('take_partial_profits_', '', $user->state);
                    $schedule = ScheduleCrypto::find($id);
                    $this->takePartialProfits($chatId, $text, $id);

                    // messages 
                    if($schedule->status == "pending"){
                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    }else{
                        // add new ids  
                        $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
                    }
                }
                // update profits
                elseif(str_starts_with($text, 'update_trade_partial_profit_')){
                    $id = str_replace('update_trade_partial_profit_', '', $text);

                    $this->updatePartialProfits($chatId, $text, $id);
                }
                // update leverage 
                else if(str_starts_with($user->state, 'update_trade_leverage_')){
                    $id = str_replace('update_trade_leverage_', '', $user->state);
                    $this->telegramMessageType("update_trade_leverage", $chatId, ["id" => $id, "leverage" => $text]);
                }
                // template name 
                elseif(str_starts_with($user->state, 'save_template_name_')){
                    $id = str_replace('save_template_name_', '', $user->state);
                    $schedule = ScheduleCrypto::find($id);

                    $template = new PartialProfitTemplate();
                    $template->user_id = $chatId;
                    $template->name = $text;
                    $template->tp1 = $schedule->partial_profits_tp1 == null ? 0 : $schedule->partial_profits_tp1;
                    $template->tp2 = $schedule->partial_profits_tp2 == null ? 0 : $schedule->partial_profits_tp2;
                    $template->tp3 = $schedule->partial_profits_tp3 == null ? 0 : $schedule->partial_profits_tp3;
                    $template->tp4 = $schedule->partial_profits_tp4 == null ? 0 : $schedule->partial_profits_tp4;
                    $template->tp5 = $schedule->partial_profits_tp5 == null ? 0 : $schedule->partial_profits_tp5;
                    $template->save();

                    // messages 
                    if($schedule->status == "pending"){
                        // $user->state = "trade_volume_question_amount_usdt";
                        // $this->telegramMessageType("trade_volume_question_amount_usdt", $chatId, ["id" => $schedule->id]);
                        $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $schedule->id]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    }else{
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => '✅ Partial profit updated successfully!',
                        ]);

                        $user->state = null;

                        // remove old message 
                        $allTelegramMsgIds = Cache::get("my_signal_update_partial_profit_message_ids_$user->id");
                        if (!empty($allTelegramMsgIds)) {
                            $allTelegramMsgIds[] = $messageId;
                            foreach ($allTelegramMsgIds as $messageId) {
                                try {
                                    Telegram::deleteMessage([
                                        'chat_id' => $chatId,
                                        'message_id' => $messageId,
                                    ]);
                                } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                                    Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: SKIP");
                                }
                            }
                        }
                        Cache::forget("my_signal_update_partial_profit_message_ids_$user->id");
                    }
                }
                // question 
                elseif(str_starts_with($user->state, 'trade_volume_question_amount_usdt_')){
                    $id = str_replace('trade_volume_question_amount_usdt_', '', $user->state);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $messageId;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

                    $this->tradeVolumeQuestionAmountUSDT($chatId, $text, $id);
                }
                elseif(str_starts_with($user->state, 'trade_volume_question_amount_coin_')){
                    $id = str_replace('trade_volume_question_amount_coin_', '', $user->state);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $messageId;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

                    $this->tradeVolumeQuestionAmountCOIN($chatId, $text, $id);
                }
                // manully update   
                else if(str_starts_with($user->state, 'signal_manually_input_')){
                    if (!is_numeric($text)) {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>Enter proper numeric values, e.g., 10, 10.11, etc:</b>
                            EOT,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '🏠 Main Menu', 'callback_data' => "main_menu"],
                                    ]
                                ]
                            ])
                        ]);
                        return;
                    }

                    $idType = str_replace('signal_manually_input_', '', $user->state);
                    $explode = explode("-", $idType);
                    $type = $explode[0];
                    $id = $explode[1];

                    // update 
                    $schedule = ScheduleCrypto::where("id", $id)->where("chat_id", $chatId)->first();
                    $schedule[$type] = $text;
                    $schedule->save();
 
                    $this->telegramMessageType("signal_manually_input", $chatId, ["id" => $id]);
                }

                // update loss
                else if(str_starts_with($user->state, 'update_trade_stop_loss_')){
                    $baseID = str_replace('update_trade_stop_loss_', '', $user->state);
                    $explode = explode("_", $baseID);
                    $base = $explode[0];
                    $id = $explode[1];
                    $stopLoss = $text;
                    // connecting ...
                    $connectingMsg = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Connecting...',
                    ]);

                    $schedule = ScheduleCrypto::where('id', $id)->first();

                    $decimals = max(strlen(substr(strrchr($schedule->entry_target, '.'), 1)), 1);
                    $stopLoss = formatNumberFlexible($stopLoss, $decimals);
                    
                    if($base != "price"){
                        $stopLoss = ($schedule->entry_target/100) * $text;
                    }

                    // Closed and Create order
                    if($schedule->market === "bybit" && $schedule->status != "pending"){
                        $takeProfit = $this->bybitAPI->updateTPSL($schedule, null, $stopLoss);

                        if(!$takeProfit["status"]){
                            Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => <<<EOT
                                <b>{$takeProfit["msg"]}</b>
                                EOT,
                                'parse_mode' => 'HTML',
                            ]);

                            // Delete "Connecting..." 
                            Telegram::deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $connectingMsg->getMessageId(),
                            ]);
                            return;
                        }
                    }

                    $schedule->stop_loss = $stopLoss;
                    $schedule->stop_loss_percentage = null;
                    $schedule->stop_loss_price = null;
                    $schedule->save();

                    $user->state = null;
                    $user->save();

                    // Delete "Connecting..." 
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $connectingMsg->getMessageId(),
                    ]);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>✅ Congratulations! Stop loss successfully updated.</b>
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    if($schedule->status === "pending"){
                        $this->telegramMessageType("edit_signal_buttons", $chatId, ["id" => $id]);
                    }
                }
                // entry point
                else if(str_starts_with($user->state, 'update_trade_entry_point_')){
                    $id = str_replace('update_trade_entry_point_', '', $user->state);
                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $entryPoint = formatNumberFlexible($text, $schedule->qty_step);
                    $user->state = null;
                    $user->save();

                    // checking 
                    if(ScheduleCrypto::where('id', $id)->where("status", "running")->exists()){
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>The trade is alreday started.</b>
                            EOT,
                            'parse_mode' => 'HTML',
                        ]);

                        return;
                    }

                    $schedule->entry_target = $entryPoint;
                    $schedule->save();

                    // check real or demo 
                    if($schedule->type === "real"){
                        // $this->telegramMessageType("trade_volume_question_amount_usdt", $chatId, ["id" => $id]);
                        $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $schedule->id]);
                        return;
                    }

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>✅ Congratulations! Entry point successfully updated.</b>
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    if($schedule->status === "pending"){
                        $this->telegramMessageType("edit_signal_buttons", $chatId, ["id" => $id]);
                    }
                }

                // trdae report 
                else if(str_starts_with($user->state, 'trade_report_loss_')){
                    $id = str_replace('trade_report_loss_', '', $user->state);

                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    $schedule->actual_profit_loss = -$text;
                    $schedule->save();

                    $user->state = null;
                    $user->save();

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>Thanks for providing your current trade info.</b>
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);
                }

                // Trailing Stop
                else if(str_starts_with($user->state, 'trailing_stop_input_')){
                    $idType = str_replace('trailing_stop_input_', '', $user->state);
                    $explode = explode("-", $idType);
                    $type = $explode[0];
                    $id = $explode[1];

                    // check it's int or not 
                    if (!is_numeric($text)) {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>Enter proper numeric values, e.g., 10, 10.11, etc:</b>
                            EOT,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '🏠 Main Menu', 'callback_data' => "main_menu"],
                                    ]
                                ]
                            ])
                        ]);
                        return;
                    }

                    $schedule = ScheduleCrypto::where('id', $id)->first();
                    if($type === "percentage_based"){
                        $schedule->stop_loss_percentage = formatNumberFlexible($text);
                    }else{
                        $schedule->stop_loss_price = formatNumberFlexible($text);
                    }
                    $schedule->save();
                    
                    $this->telegramMessageType("trailing_stop_confirm", $chatId, ["id" => $id]);
                }

                // license
                else if($user->state == "license"){
                    // Send "Connecting..." message
                    $connectingMsg = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Connecting...',
                    ]);

                    // Run license validation
                    $response = licenseValidation(trim($text), $chatId);

                    // Delete "Connecting..." message and the original message
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $connectingMsg->getMessageId(),
                    ]);

                    // delete license 
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);

                    // Respond based on license validation result
                    if (!empty($response['status'])) {
                        $this->telegramMessageType('license_activated', $chatId);
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => $response['msg'] ?? 'Unknown error occurred.',
                        ]);
                    }

                }

                /*
                =================
                Manually Track 
                =================
                */
                else if($user->state === "track_signal_manually_pair"){
                    $pair = strtoupper($text);

                    // Send "Checking..." message
                    $connectingMsg = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Checking...',
                    ]);

                    // get coin info
                    $infoResponse = Http::get("https://thechainguard.com/instruments-info", [
                        "symbol" => $pair
                    ]);
                    $jsonInfo = $infoResponse->json();

                    // price 
                    $price = combineCryptoPrices([$pair]);
                    $price = $price[$pair] ?? null;


                    // Delete "Checking..." message and the original message
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $connectingMsg->getMessageId(),
                    ]);

                    // Respond based on license validation result
                    if (empty($price)) {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'It seems the coin is missing and could not be located!',
                        ]);

                        return;
                    }

                    // save data 
                    $decimals = $jsonInfo["count"];

                    $crypto =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
                    $crypto->trade_id = now()->format('ymdihs');
                    $crypto->entry_target = $price;
                    $crypto->height_price = $price;
                    $crypto->chat_id = $chatId;
                    $crypto->instruments = $pair;
                    $crypto->qty_step = $decimals;
                    $crypto->status = "pending";
                    $crypto->save();

                    // entry point 
                    $this->telegramMessageType("track_signal_manually_entry_point", $chatId);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $messageId;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }
                else if($user->state === "track_signal_manually_entry_point"){
                    // checking ... 
                    if (!is_numeric($text)) {
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'To continue, please enter a numeric entry point.',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '🏠 Main Menu', 'callback_data' => "main_menu"],
                                    ]
                                ]
                            ])
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return;
                    }

                    // save data 
                    $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
                    $schedule->entry_target = $text;
                    $schedule->save();

                    // leverage 
                    $this->telegramMessageType("track_signal_manually_leverage", $chatId);
                }
                else if($user->state === "track_signal_manually_leverage"){
                    // checking ... 
                    if (!is_numeric($text)) {
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'To continue, please enter a numeric leverage.',
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return;
                    }

                    // save data 
                    $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
                    $schedule->leverage = $text;
                    $schedule->save();

                    // random 
                    $price = $schedule->entry_target;
                    $abs = abs($price);
                    if ($abs == 0) {
                        return [0];
                    }
                    // Calculate step based on scale
                    if ($abs < 1) {
                        $step = pow(10, floor(log10($abs)) - 1);
                    } else {
                        $step = pow(10, floor(log10($abs)) - 1);
                    }
                    // prices
                    $prices = "";
                    for ($i = 0; $i < 3; $i++) {
                        $offset = rand(-2, 2);
                        $newPrice = $price + ($offset * $step);
                        $newPrice = max($newPrice, 0);
                        $prices .= round($newPrice, 10).", ";
                    }

                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>⚖️ Leverage: </b>{$schedule->leverage}X

                        What's your first Take Profit target?

                        💡 Example: {$prices}
                        Please enter TP1 price:
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '↩️ Back', 'callback_data' => "manually_tracking_back_track_signal_manually_leverage"]
                                ]
                            ]
                        ])
                    ]);

                    $user->state = "track_signal_manually_take_profit_1";
                    $user->save();

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }
                else if(str_starts_with($user->state, 'track_signal_manually_take_profit_')){
                    $tp = (int)str_replace('track_signal_manually_take_profit_', '', $user->state);
                    $nextTP = $tp+1;
                    $prevTP = $tp-1;

                    // checking ... 
                    if (!is_numeric($text)) {
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'To continue, please enter a numeric take profit.',
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return;
                    } 

                    // save data 
                    $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
                    $schedule["take_profit{$tp}"] = $text;
                    // direction 
                    $directionTxt = "";
                    if($tp === 1){
                        if($schedule->take_profit1 > $schedule->entry_target){
                            $schedule->tp_mode = "LONG";
                            $directionTxt = "\n📈 Direction: LONG (auto-detected)";
                        }else{
                            $schedule->tp_mode = "SHORT";
                            $directionTxt = "\n📈 Direction: SHORT (auto-detected)";
                        }
                    }
                    $schedule->save();

                    // calculation 
                    $calculation = calculateFuturesProfit($schedule, null, $text);
                    $percentage = $calculation["breakdown"][0]["percentage_gain"] ?? "0%";

                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>🎯 TP{$tp}: $44,000 (+{$percentage}%)</b>{$directionTxt}

                        Would you like to add another Take Profit target?
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => "➕ Add TP{$nextTP}", 'callback_data' => "track_signal_manually_take_profit_{$nextTP}"],
                                    ['text' => '✅ Continue to Stop Loss', 'callback_data' => 'track_signal_manually_continue_to_stop_loss']
                                ],
                                [
                                    ['text' => '↩️ Back', 'callback_data' => "manually_tracking_back_track_signal_manually_leverage"]
                                ]
                            ]
                        ])
                    ]);

                    $user->state = null;
                    $user->save();

                    // $this->telegramMessageType("track_signal_manually_take_profit", $chatId, ["tp" => $tp]);
                }
                else if($user->state === "track_signal_manually_stop_loss"){
                    // checking ... 
                    if (!is_numeric($text)) {
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'To continue, please enter a numeric stop loss.',
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $messageId;
                        $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return;
                    }

                    // save data 
                    $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
                    $schedule->stop_loss = $text;
                    $schedule->save();

                    // calculation 
                    $calculation = calculateFuturesProfit($schedule, null, $text);
                    $percentage = $calculation["breakdown"][0]["percentage_gain"] ?? "0%";

                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>🛑 Stop Loss: </b>{$schedule->stop_loss}$ ({$percentage}%)

                        What's the signal provider name?

                        💡 Example: CryptoSignals, TradingPro, MyChannel
                        Please enter provider name:
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    $user->state = "track_signal_manually_provider_name";
                    $user->save();

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->getMessageId();
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }
                else if($user->state === "track_signal_manually_provider_name"){
                    $providerName =  strtolower($text);
                    $format = SignalFormat::whereRaw('LOWER(format_name) = ?', [strtolower($providerName)])->first();
                    if(empty($format)){
                        $format = new SignalFormat();
                        $format->format_name = $text;
                        $format->status = "inactive";
                        $format->save();
                    }

                    $crypto =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
                    // ✅ Collect TP values dynamically 
                    $TPs = [];
                    for ($i = 1; $i <= 10; $i++) {
                        $prop = "take_profit{$i}";
                        $tpVar = $crypto[$prop] ?? null;
                        if (!empty($tpVar)) {
                            $TPs[] = $tpVar;
                        }
                    }

                    // ✅ Calculate TP gains
                    $tpsCalculation = [];
                    foreach ($TPs as $tp) {
                        $priceDifference = $crypto->tp_mode === "LONG" ? $tp - $crypto->entry_target : $crypto->entry_target - $tp;
                        $percentageChange = $priceDifference / $crypto->entry_target;
                        $percentageGain = $percentageChange * 100 * $crypto->leverage;
                        $tpsCalculation[] = formatNumberFlexible($percentageGain, 2);
                    }

                    // ✅ Generate TP display blocks
                    $tpDisplay = '';
                    foreach ($TPs as $index => $tpValue) {
                        $gain = $tpsCalculation[$index];
                        $formattedGain = ($gain > 0 ? '+' : '') . $gain . '%';
                        $tpNum = $index + 1;
                        $tpDisplay .= "\n<b>🎯TP{$tpNum}: {$tpValue} ({$formattedGain})</b>";
                    }

                    // ✅ Calculate SL percentage loss
                    $lossCalculation = 0;
                    $lossResult = calculateFuturesProfit($crypto, null, $crypto->stop_loss);
                    if (isset($lossResult["breakdown"][0]["percentage_gain"])) {
                        $lossCalculation = $lossResult["breakdown"][0]["percentage_gain"];
                    }

                    // ✅ Prepare and send Telegram message
                    $messageResponseDetechData = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📊 I've received your signal for {$crypto->coinType}</b> 

                        <b>Provider:</b> $format->format_name
                        <b>Type:</b> $crypto->tp_mode  
                        <b>Leverage:</b> {$crypto->leverage}X  
                        <b>Entry:</b> $crypto->entry_target  
                        <b>Stop Loss:</b> $crypto->stop_loss ({$lossCalculation}%)

                        <b>Take Profit Levels:</b>{$tpDisplay}

                        Is this a real trade you're taking or just for tracking/demo purposes?
                        EOT,
                        'parse_mode' => 'HTML'
                    ]);

                    // treding market 
                    $msgresSelectType = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📊 You selected:</b> <code>{$crypto->market}</code>

                        Is this a real trade you're taking or just for tracking/demo purposes.
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '💰 Real Trade', 'callback_data' => "trade_type_real_{$crypto->id}"],
                                    ['text' => '🎮 Demo Only', 'callback_data' => "trade_type_demo_{$crypto->id}"],
                                ],
                                [
                                    ['text' => '✏️ Edit Signal', 'callback_data' => "edit_signal_{$crypto->id}"],
                                ]
                            ]
                        ])
                    ]);

                    $user->state = null;
                    $user->save();

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $messageResponseDetechData->getMessageId();
                    $trackSignalMsgIds[] = $msgresSelectType->getMessageId();
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }
            }
            
            return response('ok');
        } catch (\Throwable $th) {
            Log::info("Error: $th");
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'We encountered a network error—please try again shortly.',
            ]);
        }
    }
    /*
    TELEGRAM ALL MSG
    */
    private function telegramMessageType($type, $chatId, $data=[])
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);

        // main menu
        if($type == "main_menu"){
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚠️ 🚀 Welcome to SignalManager! </b>
                I'm your personal trading assistant that will track your signals and alert you when price targets are reached.

                ⭐ You are currently in DEMO MODE and can track up to {$this->freeSignalLimit} signals for free.

                <b>To get started:</b>
                1️⃣ Forward a trading signal from your favorite signal providers 
                2️⃣ I'll set up alerts for your signal's entry points, stop loss, and take profit levels

                Want unlimited signals and premium features?

                Visit https://signalvision.ai to get your subscription.
                EOT,
                'parse_mode' => 'HTML',

                'reply_markup' => json_encode([
                    'keyboard' => [
                        ['➕ Track Signal', '📋 My Signals'],
                        ['📊 History', '🔑 License'],
                        ['🆘 Help']
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
        }

        // help
        else if($type == "help"){
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📚 SignalManage Bot Features:</b>
                
                <b>🔍 SIGNAL TRACKING</b>
                • Track signals from any signal provider
                • Real-time price monitoring
                • Instant alerts when targets are reached
                • Support for multiple trading pairs

                <b>📊 TRADE MANAGEMENT</b>
                • Separate real and demo trades
                • Customize take profit strategies
                • Update stop loss levels in real-time
                • Track trade performance

                <b>📈 HISTORY & ANALYTICS</b>
                • Complete trading history
                • Performance statistics
                • Downloadable reports
                • Win rate and profit tracking

                <b>💳 SUBSCRIPTION</b>
                • Demo: 3 free signals
                • Premium: Unlimited signals, advanced analytics, priority support

                Need assistance? Contact support@signalvision.ai
                EOT,
                'parse_mode' => 'HTML',

                'reply_markup' => json_encode([
                    'keyboard' => [
                        ['🏠 Main Menu'],
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
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
            // demo 
            // if(is_null($user->expired_in)){
            //     $schedules = ScheduleCrypto::where("chat_id", $chatId)->get();
            //     if(count($schedules) >= 3){
            //         $this->telegramMessageType("license_limit", $chatId);
            //     }else{
            //         $this->telegramMessageType("license_demo", $chatId);
            //     }
            // }else{
                $this->telegramMessageType("license_status", $chatId);
            // }
        }
        // demo
        else if ($type == "license_demo") {
            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                🔑 <b>License Management</b>
                You are currently in <b>DEMO MODE</b> with limited functionality ({$this->freeSignalLimit} signals max).
                
                To activate your premium subscription:
                1. Visit signalvision.ai to purchase
                2. Get your license key
                3. Set Premium Key
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🌐 Go to Website', 'url' => 'https://signalvision.ai'],
                        ],
                        [
                            ['text' => '💎 Set Premium Key', 'callback_data' => 'get_premium_license']
                        ]
                    ]
                ])
            ]);
        }
        // limit 
        else if ($type == "license_limit") {
            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚠️ Demo Mode Limit Reached</b>
                
                You've reached the maximum of 10 signals for demo mode.
                
                To track more signals:
                1. Visit https://signalvision.ai to purchase a subscription
                2. Return and activate your license key
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '❌ Remove a Signal', 'callback_data' => 'go_to_list']],
                        [['text' => '💎 Set Premium Key', 'callback_data' => 'get_premium_license']],
                    ]
                ])
            ]);
        }
        
        // activated 
        else if ($type == "license_activated") {
            $expired = $expired = Carbon::parse(trim($user->expired_in));
            $now = Carbon::now();
            $diffInHours = $now->diffInHours($expired, false);
            $diffInDays = $now->diffInDays($expired, false);

            if ($diffInHours <= 0) {
                $remaining = "Expired";
            } elseif ($diffInDays < 1) {
                $remaining = "{$diffInHours}h";
            } else {
                $remaining = "{$diffInDays} days";
            }
            
            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ License Activated Successfully!</b>

                <b>Subscription Type:</b>
                {$user->subscription_type}
                <b>Expiration Date:</b>
                {$user->expired_in}
                <b>Days Remaining:</b>
                {$remaining}

                <b>You now have full access to all premium features:</b>
                - 10 signal tracking
                - Performance analytics
                - Excel reports
                - Priority alerts
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Go to /list', 'callback_data' => "go_to_list"]
                        ]
                    ]
                ])
            ]);

            $user->state = null;
            $user->save();
        }
        // status  
        else if ($type == "license_status") {
            // Send "Connecting..." message
            $connectingMsg = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            // Run license validation
            $response = Http::withHeaders([
                'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
            ])->post(config('services.api.ctypto_end_point').'/api/license/status', [
                "chat_id" => $chatId
            ]);

            // response error 
            if (!$response->successful()) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Something went wrong. Please try again later.",
                ]);
            }

            // Delete "Connecting..." 
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            // const data  
            $signalExpiredIn = $response['expired_in'];
            $signalActivationIn = $response['activation_in'];
            $signalPeriod = $response['period'];
            $now = Carbon::now();
            
            // SignalManager 
            $expired = $expired = Carbon::parse(trim($user->expired_in));

            $diffInHours = $now->diffInHours($expired, false);
            $diffInDays = $now->diffInDays($expired, false);

            if ($diffInHours <= 0) {
                $remaining = "Expired";
                $status = "❌ Expired";
            } elseif ($diffInDays < 1) {
                $remaining = "<b>Hours Remaining:</b> \n{$diffInHours}h";
                $status = "✅ Expiring Soon";
            } else {
                $remaining = "<b>Days Remaining:</b> \n{$diffInDays} days";
                $status = "✅ Active";
            }

            // SignalTradr 
            if(!empty($response['expired_in'])){
                $expiredTrader = $expired = Carbon::parse(trim($signalExpiredIn));

                $diffInHoursTrader = $now->diffInHours($expiredTrader, false);
                $diffInDaysTrader = $now->diffInDays($expiredTrader, false);

                if ($diffInHoursTrader <= 0) {
                    $statusTrader = "❌ Expired";
                } elseif ($diffInDaysTrader < 1) {
                    $statusTrader = "✅ Expiring Soon";
                } else {
                    $status = "✅ Active";
                    $statusTrader = "✅ Active";
                }
            }else{
                $statusTrader = "❌ Not Active";
            }

            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🔑 Your License Status:</b>

                <b>Subscription Type:</b>
                {$user->subscription_type}
                <b>Activation Date:</b>
                $user->activation_in
                <b>Expiration Date:</b>
                $user->expired_in
                {$remaining}

                <b>Bot Access:</b>
                - SignalManager: {$status}
                - SignalShot: {$statusTrader}

                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Renew License', 'callback_data' => 'get_premium_license']
                        ],
                        [
                            ['text' => '📋 Go to /list', 'callback_data' => 'go_to_list']
                        ]
                    ]
                ])
            ]);
        }
        // premium_limit
        else if ($type == "license_premium_limit") {
            // Individual Trades
            $schedules = ScheduleCrypto::where('chat_id', $chatId)
            ->whereIn('status', ['running', 'waiting'])
            ->get();

            if ($schedules->isEmpty()) {
                $messages = ["No list found!"];
            }

            $messages = [];
            foreach ($schedules as $index => $value) {
                $pair = $value->instruments ?? 'BTCUSDT';
                $tradeMode = $value->tp_mode ? strtoupper(substr($value->tp_mode, 0, 1)) : 'N';
                $date = $value->created_at ? Carbon::parse($value->created_at)->format('M j') : 'May 1';
        
                $messages[] = "{$pair} {$tradeMode} Started: {$date}";
            }

            $messageText = implode("\n", $messages);

            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚠️ SIGNAL TRACKING LIMIT REACHED ⚠️</b>
                
                You currently have 10 active signals being tracked, which is the maximum allowed with your current license.
                
                📊 To track more signals, you can close one or more of your current signals to free up tracking slots.

                🔄 Your active signals:
                {$messageText}
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '❌ Remove a Signal', 'callback_data' => 'go_to_list']],
                    ]
                ])
            ]);
        }
        // connection erre  
        else if ($type == "license_connection_err") {
            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚠️ Connection Error</b>
                
                Connection error, please try again later!
                EOT,
                'parse_mode' => 'HTML'
            ]);
        }


        /*
        ==========================
        My Signals
        ==========================
        */
        // My Signals
        else if($type == "my_signals"){
            $messageIds = [];

            // Get all latest running trades
            Cache::forget('schedule_cryptos_running_waiting');
            $allSchedules = Cache::remember("schedule_cryptos_running_waiting", now()->addHours(1), function () {
                return ScheduleCrypto::whereIn('status', ['running', 'waiting'])->orderBy('id', 'desc')->get();
            });

            $schedules = collect($allSchedules->where('chat_id', $chatId)->sortByDesc('id') ?? []);

            // If No Active Trades
            if(count($schedules) < 1){
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📋 You have no active trades.</b>

                    Forward a signal to start tracking.
                    EOT,
                    'parse_mode' => 'HTML'
                ]);
                $messageIds[] = $msgresInfo->getMessageId();


                $allTelegramMsgIds = Cache::get('my_signal_message_ids', []);
                if (!empty($allTelegramMsgIds)) {
                    $allTelegramMsgIds[] = $data["msg_id"];

                    foreach ($allTelegramMsgIds as $messageId) {
                        try {
                            Telegram::deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $messageId,
                            ]);
                        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                            Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: 5002");
                        }
                    }
                }
                Cache::forget('my_signal_message_ids');
                Cache::forever('my_signal_message_ids', $messageIds);

                return "ok";
            }

            // Send "Checking..." message
            $connectingMsg = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            // get real position & order
            if ($schedules->where('type', 'real')->where('market', 'bybit')->isNotEmpty()) {
                $ordersPositionsData = $this->bybitAPI->getOrderOrPosition($chatId);
                $orders    = $ordersPositionsData['data']['orders']    ?? [];
                $positions = $ordersPositionsData['data']['positions'] ?? [];
            }

            $orders    = collect($orders ?? []);
            $positions = collect($positions ?? []);

            $cryptoPrices = cryptoPrices();
            $messageIds = [];
            foreach ($schedules as $trade) {
                try {
                    $SignalFormat = SignalFormat::find($trade->provider_id);
                    if(isset($SignalFormat->format_name)){
                        $format_name = $SignalFormat->format_name;
                    }else{
                        $format_name = "Unknown";
                    }

                    $buttons = [];
                    $type = $trade->type;
                    $status = ucfirst($trade->status);
                    $market = $trade->market;
                    $instrument = $trade->instruments;

                    $currentPrice = $cryptoPrices[$instrument] ?? 'Working';

                    $entry = $trade->entry_target;
                    $stopLoss = $trade->stop_loss;
                    $leverage = $trade->leverage;
                    $positionSize = $trade->position_size_usdt;
                    $investmentSign = "USDT";
                    $investment = $positionSize;
                    $viewType = strtoupper($type);
                    $viewMarket = strtoupper($market);
                    
                    /*
                    ================
                    P&L
                    ================
                    */
                    $finalResultPnL = "";
                    $liqPrice = "";
                    $formattedFinalPnL = 0;

                    // REAL 
                    if($viewType === "REAL"){
                        $side = $trade->tp_mode === "LONG" ? "Buy" : "Sell";
                        $order    = $orders->where('orderId', $trade->order_id);
                        $position = $positions->where('symbol', $trade->instruments)->where('side', $side);

                        // closed when both are gone
                        if ($order->isEmpty() && $position->isEmpty()) {
                            $trade->status = 'closed';
                            $trade->save();
                            continue;
                        }else {
                            // positions open 
                            if($position->isNotEmpty() && $order->isEmpty()){
                                $trade->leverage = $position[0]['leverage'];
                                $trade->status = 'running';
                                $trade->save();
                            }else{
                                $trade->status = 'waiting';
                                removeTradeFromCache($trade->id);
                            }

                            // Liq
                            if ($trade->status === "running" && $order->isEmpty()) {
                                $formattedFinalPnL = isset($position[0]["unrealisedPnl"]) ? formatNumberFlexible($position[0]["unrealisedPnl"], 2) : 0;
                                $profitResult = $formattedFinalPnL > 0 ? "🟢 +$formattedFinalPnL" : "🛑 ".$formattedFinalPnL;
                                $finalResultPnL = "\n<b>📊 Current P/L: </b>{$profitResult} USDT";

                                $liqPrice = isset($position[0]["liqPrice"]) ? formatNumberFlexible($position[0]["liqPrice"], 2) : 0;
                                $liqPrice = "\n<b>🛑 Liq. Price: </b>{$liqPrice} USDT\n";
                            }
                        }
                    }
                    // Demo 
                    else{
                        // partial 
                        $secureProfit = 0;
                        $totalGain = 0;
                        if ($trade->profit_strategy === "partial_profits") {
                            $partialProfits = [];
                            for ($i = 1; $i <= 10; $i++) {
                                $tpField = "take_profit{$i}";
                                $tpPrice = $trade->$tpField;
                                $val = $trade->{"partial_profits_tp{$i}"} ?? null;
                                if (!is_null($val) && $val != 0) {
                                    $partialProfits[$tpPrice] = $val;
                                }
                            }

                            $calculation = calculateFuturesProfit($trade, $partialProfits, null);
                            $totalPartialPercentage = 0;

                            foreach ($calculation["breakdown"] as $index => $breakdown) {
                                if (($index + 1) <= $trade->height_tp) {
                                    $secureProfit += $breakdown["profit"];
                                    $totalGain += $breakdown["percentage_gain"];
                                    $totalPartialPercentage += $breakdown["partial"];
                                }
                            }

                            $remainingPercentage = 100 - $totalPartialPercentage;
                            $remainingInvestment = ($remainingPercentage / 100) * $investment;

                            // profit 
                            if ($trade->tp_mode === "LONG") {
                                $priceDifference = $currentPrice - $entry;
                            } else { // SHORT
                                $priceDifference = $entry - $currentPrice;
                            }

                            $percentageChange = $priceDifference / $entry;
                            $profit = $remainingInvestment * $percentageChange * $leverage;

                            $finalResultAmount = formatNumberFlexible($profit + $secureProfit);
                        } else {     
                            $calculateProfit = calculateFuturesProfit($trade, null, $currentPrice);
                            $profitAmount = $calculateProfit["breakdown"][0]["profit"] ?? 0;
                            $finalResultAmount = formatNumberFlexible($profitAmount);
                        }
                        $formattedFinalPnL = formatNumberFlexible($finalResultAmount, 2);

                        if ($trade->status !== "waiting") {
                            $profitResult = $formattedFinalPnL > 0 ? "🟢 +$formattedFinalPnL" : "🛑 ".$formattedFinalPnL;
                            $finalResultPnL = "\n\n<b>📊 Current P/L: </b>{$profitResult} USDT";
                        }
                    }

                    // update pnl 
                    $trade->actual_profit_loss = $formattedFinalPnL;
                    $trade->save();

                    // trailing stop 
                    $trailingStopTxt = "";
                    if(!empty($trade->stop_loss_percentage) || !empty($trade->stop_loss_price)){
                        if(!empty($trade->stop_loss_percentage)){
                            $trailingMethod = "Percentage-Based";
                            $trailingDistance = $trade->stop_loss_percentage."%";
                        }else{
                            $trailingMethod = "Price-Based";
                            $trailingDistance = $trade->stop_loss_price."USDT";
                        }

                        $trailingStopTxt = "\n📉 Trailing: <code>{$trailingMethod}, {$trailingDistance}</code>";
                    }

                    // Highest TP Reached
                    $heightTpMsg = '';
                    if (!is_null($trade->height_tp)) {
                        $index = $trade->height_tp;
                        $tpField = "take_profit{$index}";
                        if (!empty($trade->$tpField)) {
                            $highestTP = $trade->$tpField;
                            $heightTpMsg = "\n<b>🚀 Highest TP Reached:</b> <code>TP{$index}</code> (<code>{$highestTP}</code>)";
                        }
                    }

                    // TP Message Construction
                    $tpLines = [];
                    $secureProfit = 0;
                    if ($trade->profit_strategy === "partial_profits") {
                        $partialProfits = [];
                        for ($i = 1; $i <= 10; $i++) {
                            $tpField = "take_profit{$i}";
                            $tpPrice = $trade->$tpField;
                            $val = $trade->{"partial_profits_tp{$i}"} ?? null;

                            if (!empty($val)) {
                                $partialProfits[$tpPrice] = $val;
                            }
                        }

                        $calculation = calculateFuturesProfit($trade, $partialProfits, null);
                        foreach ($calculation["breakdown"] as $index => $breakdown) {
                            $currentTp = $index + 1;
                            $tpDisplay = $breakdown["tp"];
                            $partialPercent = $breakdown["partial"];

                            $secureLine = '';
                            if (!is_null($trade->height_tp) && $trade->height_tp >= $currentTp) {
                                $gainPartial = $breakdown["percentage_gain"];
                                $profitPartial = $breakdown["profit"];
                                $secureProfit += $profitPartial;
                                $secureLine = "\n➤ +{$gainPartial}% | Secured: +" . number_format($profitPartial, 2) . " USDT";
                            }

                            // profit per tp 
                            $tpCalculation = calculateFuturesProfit($trade, null, $tpDisplay);
                            $tpCalculationProfit = $tpCalculation["breakdown"][0]["profit"];
                            $tpCalculationGain = $tpCalculation["breakdown"][0]["percentage_gain"];
                            $profitText = $tpCalculationProfit > 0 ? '+' . formatNumberFlexible($tpCalculationProfit, 2) : formatNumberFlexible($tpCalculationProfit, 2); // {$tpCalculationGain}%

                            $tpLines[] = "🎯 TP{$currentTp}: {$tpDisplay} <b>({$profitText}USDT)</b>".$secureLine;
                        }
                    } else {
                        for ($i = 1; $i <= 10; $i++) {
                            $tpField = "take_profit{$i}";
                            $tpPrice = $trade->$tpField;
                            if(!empty($tpPrice)){
                                $tpCalculation = calculateFuturesProfit($trade, null, $tpPrice);
                                $tpCalculationProfit = $tpCalculation["breakdown"][0]["profit"];
                                $tpCalculationGain = $tpCalculation["breakdown"][0]["percentage_gain"];
                                $profitText = $tpCalculationProfit > 0 ? '+' . number_format($tpCalculationProfit, 2) : number_format($tpCalculationProfit, 2); // {$tpCalculationGain}%
                                
                                $tpLines[] = "🎯 TP{$i}: {$tpPrice} <b>({$profitText}USDT)</b>";
                            }
                        }
                    }

                    // stop loss cal 
                    $slCalculateProfit = calculateFuturesProfit($trade, null, $stopLoss);
                    $slProfitAmount = $slCalculateProfit["breakdown"][0]["profit"] + $secureProfit;

                    // final 
                    $slProfitAmount = number_format($slProfitAmount, 2);

                    $secureMsg = $secureProfit > 0 ? "\n\n<b>Total secured profit:</b> <code>" . number_format($secureProfit, 2) . " {$investmentSign}</code>" : '';

                    $tpSection = implode("\n", $tpLines);

                    // stretegy 
                    $strategy = ucfirst(str_replace("_", " ", $trade->profit_strategy));
                    $partialPercents = "";
                    if ($trade->profit_strategy === "partial_profits") {
                        $partials = [];
                        for ($i = 1; $i <= 10; $i++) {
                            $tp = $trade->{'partial_profits_tp' . $i} ?? null;
                            if (!empty($tp)) {
                                $partials[] = "TP{$i}: {$tp}%, ";
                            }
                        }
                        $partialPercents = "\n" . rtrim(implode('', $partials), ', ');
                    }

                    // close specific tp 
                    if ($trade->profit_strategy === "close_specific_tp") {
                        $strategy = "Close at TP{$trade->specific_tp}";
                    }

                    // running 
                    $tradeStartedTxt = "";
                    if($trade->status === "running"){
                        $tradeStartedTxt = "";
                        // try {
                            $tradeStarted = Carbon::parse($trade['trade_entry']);
                            $tradeDuration = Carbon::now()->diff($tradeStarted);
                            $days = !empty($tradeDuration->d) ? $tradeDuration->d."d" : "";
                            $hours = !empty($tradeDuration->h) ? $tradeDuration->h."h" : "";
                            $minutes = !empty($tradeDuration->i) ? $tradeDuration->i."m" : "";
                            $seconds = !empty($tradeDuration->s) ? $tradeDuration->s."s" : "";

                            $tradeStartedTxt = "\n<b>🗓️ Trade Start:</b> {$days} {$hours} {$minutes} {$seconds}";
                        // } catch (\Exception $e) {
                        //     $tradeStartedTxt = "\n<b>🗓️ Trade Start:</b> Invalid date";
                        // }
                    }else{
                        $tradeStartedTxt = "";
                        try {
                            $tradeStarted = Carbon::parse($trade['created_at']);
                            $tradeDuration = Carbon::now()->diff($tradeStarted);
                            $days = !empty($tradeDuration->d) ? $tradeDuration->d."d" : "";
                            $hours = !empty($tradeDuration->h) ? $tradeDuration->h."h" : "";
                            $minutes = !empty($tradeDuration->i) ? $tradeDuration->i."m" : "";
                            $seconds = !empty($tradeDuration->s) ? $tradeDuration->s."s" : "";

                            $tradeStartedTxt = "\n<b>🗓️ Trade Created:</b> {$days} {$hours} {$minutes} {$seconds}";
                        } catch (\Exception $e) {
                            $tradeStartedTxt = "\n<b>🗓️ Trade Created:</b> Invalid date";
                        }
                    }
                    
                    $messageText = <<<EOT
                    <b>{$trade->instruments} | {$trade->tp_mode} | {$viewType} | {$viewMarket}</b>

                    <b>⚙️ Trade ID:</b> {$trade->trade_id}
                    <b>🤝 Provider:</b> {$format_name}
                    <b>🎯 Status:</b> {$status}
                    <b>🎯 Investment:</b> <code>{$investment}</code> {$investmentSign}
                    <b>🎯 Entry:</b> <code>{$entry}</code>
                    <b>💵 Current Price:</b> <code>{$currentPrice}</code>
                    <b>🛑 Stop Loss:</b> <code>{$stopLoss}</code>{$trailingStopTxt}
                    <b>🛑 Risk:</b> <code>{$slProfitAmount} {$investmentSign}</code>{$tradeStartedTxt}
                    <b>⚙️ Leverage:</b> <code>{$trade->leverage}</code>X {$heightTpMsg}
                    <b>⚙️ Strategy:</b> {$strategy}{$partialPercents}{$liqPrice}{$finalResultPnL}

                    <b>Take Profit Targets:</b>
                    {$tpSection}{$secureMsg}
                    EOT;

                    $response = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => [
                            [
                                ['text' => '❌ Close', 'callback_data' => "close_trade_{$trade->id}"],
                                ['text' => '⚙️ Update', 'callback_data' => "update_trade_buttons_{$trade->id}"],
                            ]
                        ]])
                    ]);

                    $messageIds[] = $response->getMessageId();
                } catch (\Throwable $th) {
                    Log::info("My Signal Error: $th");
                }
            }

            // Send it via Telegram bot with HTML formatting
            $statuses = ['running', 'waiting'];

            $totalActive = $schedules->where("status", "running")
                ->count();

            $totalReal = $schedules->whereIn("status", $statuses)
                ->where("type", "real")
                ->count();

            $totalDemo = $schedules->whereIn("status", $statuses)
                ->where("type", "demo")
                ->count();


            $messageResponseTotal = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "You have $totalActive active trades. $totalReal real and $totalDemo demo.",
                'reply_markup' => json_encode([
                    'keyboard' => [
                        ['🔄 Refresh', '➕ Track Signal'],
                        ['📊 History', '🏠 Main Menu']
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
            $messageIds[] = $messageResponseTotal->getMessageId();

            // Delete "Checking..." message and the original message
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            // remove old message 
            $allTelegramMsgIds = Cache::get('my_signal_message_ids', []);
            if (!empty($allTelegramMsgIds)) {
                $allTelegramMsgIds[] = $data["msg_id"];
                foreach ($allTelegramMsgIds as $messageId) {
                    try {
                        Telegram::deleteMessage([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                        ]);
                    } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                        Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: 5003");
                    }
                }
            }
            Cache::forget('my_signal_message_ids');
            Cache::forever('my_signal_message_ids', $messageIds);
        }
        // update button 
        else if($type == "update_trade_buttons"){
            $buttons = [];
            $id = $data["id"];
            $trade = ScheduleCrypto::find($id);
            if(empty($trade)){
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⚠️ This trade was already closed.",
                ]);
                return;
            }

            // update stretegy 
            $buttons[] = [['text' => '📊 Strategy', 'callback_data' => "trade_type_{$trade->type}_{$trade->id}"]];


            // Action Buttons
            array_unshift($buttons, [['text' => '🛑 Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]]);
            if ($trade->status === "waiting") {
                $buttons[] = [['text' => '🎯 Entry Point', 'callback_data' => "update_trade_entry_point_{$trade->id}"]];
                $buttons[] = [['text' => '🚀 Market Entry', 'callback_data' => "update_trade_market_entry_{$trade->id}"]];
                $buttons[] = [['text' => '⚖️ Leverage', 'callback_data' => "update_trade_leverage_{$trade->id}"]];
            }
            $buttons[] = [['text' => '❌ Cancel', 'callback_data' => "update_trade_cancel"]];

            // trailing stop 
            array_unshift($buttons, [['text' => '⏹️ Trailing', 'callback_data' => "update_trade_trailing_stop_{$trade->id}"]]);

            $flatButtons = [];
            foreach ($buttons as $btnRow) {
                // $btnRow is [button], so take first element
                $flatButtons[] = $btnRow[0];
            }
            $allButtons = array_chunk($flatButtons, 2);

            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚙️ Update Settings </b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $allButtons])
            ]);
        }
        // close trade
        else if($type == "close_trade"){
            $schedule = ScheduleCrypto::find($data['id']);
            if (empty($schedule)) {
                return;
            }

            $instrument = $schedule->instruments;
            $cryptoPrices = cryptoPrices();
            $currentPrice = $cryptoPrices[$instrument] ?? 'Working';

            $headerMsg = $schedule->status === 'waiting'
                ? '❓ Are you sure you want to cancel this trade?'
                : '❓ Are you sure you want to close this trade?';
            $buttonMsg = $schedule->status === 'waiting'
                ? '✅ Yes, Cancel Trade'
                : '✅ Yes, Close Trade';

            $message = <<<EOT
            <b>{$headerMsg}</b>

            <b>Pair:</b> {$schedule->instruments}
            <b>Investment:</b> {$schedule->position_size_usdt} USDT
            <b>Leverage:</b> {$schedule->leverage}X
            <b>TP Mode:</b> {$schedule->tp_mode}
            <b>Entry:</b> {$schedule->entry_target}
            <b>Current Price:</b> {$currentPrice}
            <b>Stop Loss:</b> {$schedule->stop_loss}
            <b>PnL:</b> {$schedule->actual_profit_loss}
            EOT;

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => $buttonMsg, 'callback_data' => "yes_close_trade_{$data['id']}"],
                        ['text' => '↩️ Cancel', 'callback_data' => 'cancel_close_trade']
                    ]]
                ])
            ]);
        }
        // update loss
        else if($type == "update_trade_loss"){
            $schedule = ScheduleCrypto::find($data['id']);
            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🛑 Stop Loss Configuration</b>

                <b>Current:</b> {$schedule->stop_loss}

                How would you like to set your stop loss?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💵 By Price', 'callback_data' => "by_update_trade_loss_price_{$data['id']}"],
                            ['text' => '📊 By Percentage', 'callback_data' => "by_update_trade_loss_percentage_{$data['id']}"],
                        ],
                        [
                            ['text' => '❌ Back', 'callback_data' => "update_trade_back_{$data['id']}"],
                        ],
                    ]
                ])
            ]);
        }
        // update entry points  
        else if($type == "update_trade_entry_point"){
            $schedule = ScheduleCrypto::find($data['id']);
            $prices = $this->randomPrices($schedule->height_price) ?? null;

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Limit Order Setup</b>

                Enter your limit price:
                <b>Example:</b> {$prices}
                <b>Current:</b> {$schedule->entry_target}
                EOT,
                'parse_mode' => 'HTML',
            ]);
        }
        // update market price 
        else if($type == "update_trade_market_entry"){
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>⚡ Market Entry Selected</b>

                You'll enter at current market price.
                No specific entry price needed.

                Confirm market entry?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Confirm', 'callback_data' => "confirm_update_trade_market_entry_{$data['id']}"],
                            ['text' => '❌ Back', 'callback_data' => "update_trade_back_{$data['id']}"],
                        ],
                    ]
                ])
            ]);
        }
        // update partial 
        else if($type == "update_trade_partial_profit"){
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Please specify the percentage to close at TP{$data['tp']}:</b>
                
                (Click a button or type a custom percentage directly)
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '10%', 'callback_data' => "update_partial_profits_percentage_{$data['id']}_10"],
                            ['text' => '20%', 'callback_data' => "update_partial_profits_percentage_{$data['id']}_20"],
                            ['text' => '25%', 'callback_data' => "update_partial_profits_percentage_{$data['id']}_25"],
                        ],
                        [
                            ['text' => '50%', 'callback_data' => "update_partial_profits_percentage_{$data['id']}_50"],
                            ['text' => '75%', 'callback_data' => "update_partial_profits_percentage_{$data['id']}_75"],
                            ['text' => '100%', 'callback_data' => "update_partial_profits_percentage_{$data['id']}_100"],
                        ]
                    ]
                ])
            ]);
            
            // Safely track message ID in cache
            $trackSignalMsgIds = Cache::get('update_trade_partial_profit_' . $user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('update_trade_partial_profit_' . $user->id, $trackSignalMsgIds);
        }
        // update leverage 
        else if($type == "update_trade_leverage"){
            $leverage = $data["leverage"];
            $id = $data["id"];
            if (!is_numeric($id)) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🚀 What's the new leverage?</b>
    
                    <b>Please enter:</b>
                    - The leverage in Digit (e.g., 100, 10.10)
                    EOT,
                    'parse_mode' => 'HTML',
                ]);

                return;
            }

            $schedule = ScheduleCrypto::where('id', $id)->first();
            $schedule->leverage = formatNumberFlexible($leverage);
            $schedule->save();

            // real trade 
            if($schedule->type === "real"){
                // connecting ...
                $connectingMsg = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Connecting...',
                ]);

                // Closed and Create order
                if($schedule->market === "bybit"){
                    $closedOrder = $this->bybitAPI->closeOrder($schedule);
                    $orderPlace = $this->bybitAPI->createOrder($schedule);
                }

                // Delete "Connecting..." 
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $connectingMsg->getMessageId(),
                ]);

                // false 
                if(!$closedOrder["status"]){
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $closedOrder["msg"],
                    ]);
                }
                if(!$orderPlace["status"]){
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $orderPlace["msg"],
                    ]);
                }
            }

            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Thanks for update your leverage.</b>
                EOT,
                'parse_mode' => 'HTML',
            ]);

            if($schedule->status === "pending"){
                $this->telegramMessageType("edit_signal_buttons", $chatId, ["id" => $id]);
            }
        }
        // slect trade mod 
        else if($type == "select_trade_mod"){
            $id = $data["id"];

            $user->state = null;
            $schedule = ScheduleCrypto::find($id);

            // strategy 
            $strategy = Cache::get("uni_strategy_{$user->chat_id}");
            if($strategy["strategy_status"] !== "inactive"){
                $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $id]);
                return;
            }

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 You selected:</b> <code>{$schedule->market}</code>

                Is this a real trade you're taking or just for tracking/demo purposes.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💰 Real Trade', 'callback_data' => "trade_type_real_{$id}"],
                            ['text' => '🎮 Demo Only', 'callback_data' => "trade_type_demo_{$id}"],
                        ],
                        [
                            ['text' => '✏️ Edit Signal', 'callback_data' => "edit_signal_{$id}"],
                        ]
                    ]
                ])
            ]);
        }
        // Trailing Stop
        else if($type == "update_trade_trailing_stop"){
            $id = $data['id'];
            $type = $data['type'];

            // base on type 
            if($type == "percentage_based"){
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📊 Percentage-Based Trailing Stop</b>
                    
                    A percentage-based trailing stop follows the price at a fixed percentage distance.

                    For example, a 2% trailing stop on a LONG trade will move the stop loss up as the price increases, always maintaining a 2% distance from the highest price reached.

                    <b>Select a percentage or enter a custom value:</b>
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '1%', 'callback_data' => "trailing_stop_input_percentage_based-{$data['id']}-1"],
                                ['text' => '2%', 'callback_data' => "trailing_stop_input_percentage_based-{$data['id']}-2"],
                                ['text' => '3%', 'callback_data' => "trailing_stop_input_percentage_based-{$data['id']}-3"],
                            ],
                            [
                                ['text' => '5%', 'callback_data' => "trailing_stop_input_percentage_based-{$data['id']}-5"],
                                ['text' => '10%', 'callback_data' => "trailing_stop_input_percentage_based-{$data['id']}-10"],
                                ['text' => 'Custom', 'callback_data' => "trailing_stop_input_percentage_based-{$data['id']}-custom"],
                            ]
                        ]
                    ])
                ]);
            }else{
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>💰 Price-Based Trailing Stop</b>
                    
                    A price-based trailing stop follows the price at a fixed price distance.

                    For example, a $100 trailing stop on BTC LONG will move the stop loss up as BTC price increases, always maintaining a $100 distance from the highest price reached.

                    <b>Enter the price distance (in USDT):</b>
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '50 USDT', 'callback_data' => "trailing_stop_input_price_based-{$data['id']}-50"],
                                ['text' => '100 USDT', 'callback_data' => "trailing_stop_input_price_based-{$data['id']}-100"],
                                ['text' => '200 USDT', 'callback_data' => "trailing_stop_input_price_based-{$data['id']}-200"],
                            ],
                            [
                                ['text' => '500 USDT', 'callback_data' => "trailing_stop_input_price_based-{$data['id']}-500"],
                                ['text' => '1000 USDT', 'callback_data' => "trailing_stop_input_price_based-{$data['id']}-1000"],
                                ['text' => 'Custom', 'callback_data' => "trailing_stop_input_price_based-{$data['id']}-custom"],
                            ]
                        ]
                    ])
                ]);
            }
        }
        else if($type == "trailing_stop_input"){
            $id = $data['id'];
            $type = $data['type'];
            $value = $data['value'];

            $schedule = ScheduleCrypto::latest()
            ->where("chat_id", $chatId)
            ->where("id", $id)
            ->first();

            // base on type 
            if($type == "percentage_based"){
                $schedule->stop_loss_price = null;
                $schedule->stop_loss_percentage = $value;
            }else{
                $schedule->stop_loss_price = $value;
                $schedule->stop_loss_percentage = null;
            }

            $schedule->save();

            $this->telegramMessageType("trailing_stop_confirm", $chatId, ["id" => $id]);
        }
        else if($type == "trailing_stop_input_custom"){
            $id = $data['id'];
            $type = $data['type'];

            $user->state = "trailing_stop_input_{$type}-{$id}";
            $user->save();

            if($type == "percentage_based"){
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📊 Percentage-Based Trailing Stop</b>
                    
                    A percentage-based trailing stop follows the price at a fixed percentage distance.

                    For example, a 2% trailing stop on a LONG trade will move the stop loss up as the price increases, always maintaining a 2% distance from the highest price reached.

                    <b>Select enter a custom value (eg: 0-100):</b>
                    EOT,
                    'parse_mode' => 'HTML',
                ]);
            }else{
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>💰 Price-Based Trailing Stop</b>
                    
                    A price-based trailing stop follows the price at a fixed price distance.

                    For example, a $100 trailing stop on BTC LONG will move the stop loss up as BTC price increases, always maintaining a $100 distance from the highest price reached.

                    <b>Enter the price distance (in USDT):</b>
                    EOT,
                    'parse_mode' => 'HTML',
                ]);
            }
        }
        else if($type == "trailing_stop_confirm"){
            $id = $data['id'];

            $schedule = ScheduleCrypto::latest()
            ->where("chat_id", $chatId)
            ->where("id", $id)
            ->first();

            $mode = strtoupper($schedule->tp_mode);
            $instrument = $schedule->instruments;

            $cryptoPrices = cryptoPrices();
            $currentPrice = $cryptoPrices[$instrument] ?? 0;

            if(!empty($schedule->stop_loss_percentage) || !empty($schedule->stop_loss_price)){
                if(!empty($schedule->stop_loss_percentage)){
                    $trailingMethod = "Percentage-Based";
                    $trailingDistance = $schedule->stop_loss_percentage."%";
                }else{
                    $trailingMethod = "Price-Based";
                    $trailingDistance = $schedule->stop_loss_price."USDT";
                }
            }

            // calculation stop loss
            if(!empty($schedule->stop_loss_percentage)){
                if($mode === 'LONG'){
                    $newStopLoss = formatNumberFlexible($currentPrice - (($currentPrice/100)*$schedule->stop_loss_percentage));
                }else{
                    $newStopLoss = formatNumberFlexible($currentPrice + (($currentPrice/100)*$schedule->stop_loss_percentage));
                }

                $schedule->stop_loss = $newStopLoss;
            }else{
                if($mode === 'LONG'){
                    $newStopLoss = formatNumberFlexible($currentPrice - $schedule->stop_loss_price);
                }else{
                    $newStopLoss = formatNumberFlexible($currentPrice + $schedule->stop_loss_price);
                }
                $schedule->stop_loss = $newStopLoss;
            }

            $schedule->height_price = null;
            $schedule->save();

            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ Trailing Stop Set Successfully!</b>

                <b>Current Trade: <code>{$instrument} {$schedule->tp_mode}</code></b>
                <b>Current Price: <code>{$currentPrice}</code></b>
                <b>Method: <code>{$trailingMethod}</code></b>
                <b>Distance: <code>{$trailingDistance}</code></b>
                <b>Activation: <code>Immediate</code></b>

                Your stop loss will now automatically adjust as the price moves in your favor, maintaining a <code>{$trailingMethod} {$trailingDistance}</code> distance from the highest price reached.

                <b>Current Stop Loss: <code>{$schedule->stop_loss}</code></b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [
                    [
                        ['text' => '🏠 Main Menu', 'callback_data' => "main_menu"]
                    ]
                ]])
            ]);
        }

        /*
        =====================
        Manually Trade 
        =====================
        */
        // track signal
        else if($type == "track_signal"){
            // license check  
            $license = licenseCheck($chatId);
            if(!$license["status"]){
                $this->telegramMessageType($license["type"], $chatId);
                return "ok";
            }

            // treding market 
            $messageResponseTredingMarkets = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Please select the exchange you're trading on:",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Bybit', 'callback_data' => "track_signal_manually_exchange_bybit"],
                            ['text' => 'Binance', 'callback_data' => "track_signal_manually_exchange_binance"],
                        ]
                    ]
                ])
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $messageResponseTredingMarkets->getMessageId();
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }

        // new pair 
        else if($type == "track_signal_manually_pair"){
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 Manual Signal Entry</b>

                Let's create a signal step by step.

                What trading pair do you want to track?

                💡 Example: BTCUSDT, ETHUSDT, ADAUSDT
                Please enter the pair:
                EOT,
                'parse_mode' => 'HTML',
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->getMessageId();
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

            $user->state = "track_signal_manually_pair";
            $user->save();
        }
        
        // target profit 
        else if($type == "track_signal_manually_take_profit"){
            $tp = $data["tp"];
            $prevTP = $tp-1;

            // back leverage 
            if($prevTP === 0){
                $this->telegramMessageType("track_signal_manually_leverage", $chatId);
            }

            // save data 
            $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 TP{$tp} target</b>

                Please enter TP{$tp} price:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '↩️ Back', 'callback_data' => "manually_tracking_back_track_signal_manually_take_profit_{$prevTP}"]
                        ]
                    ]
                ])
            ]);

            $user->state = "track_signal_manually_take_profit_{$tp}";
            $user->save();

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->getMessageId();
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        // entry point
        else if($type == "track_signal_manually_entry_point"){
            $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();

            $price = $schedule->height_price;
            $pair = $schedule->instruments;

            // entry point
            $prices = $this->randomPrices($price);
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 {$pair} selected</b>

                <b>Current price:</b> {$price}

                What's your entry point?

                💡 Example: {$prices}
                Please enter entry price:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '↩️ Back', 'callback_data' => 'manually_tracking_back_track_signal_manually_pair'],
                            ['text' => '🚀 Market Entry', 'callback_data' => "manually_tracking_market_entry_{$schedule->id}"]
                        ]
                    ]
                ])
            ]);
            $user->state = "track_signal_manually_entry_point";
            $user->save();

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->getMessageId();
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        // leverage 
        else if($type == "track_signal_manually_leverage"){
            $schedule =  ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
            if($schedule->last_alert  === "market_entry"){
                $marketEntry = "Market Entry";
            }else{
                $marketEntry = $schedule->entry_target;
            }

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>🎯 Entry: </b>{$marketEntry}

                What leverage do you want to use?

                💡 Example: 5, 10, 20
                Please enter leverage:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '↩️ Back', 'callback_data' => 'manually_tracking_back_track_signal_manually_entry_point']
                        ]
                    ]
                ])
            ]);

            $user->state = "track_signal_manually_leverage";
            $user->save();

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->getMessageId();
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        
        /*
        =====================
        ADD NEW TRADE
        =====================
        */
        // trade type  
        else if($type == "trade_type"){
            $id = $data["id"];
            $schedule = ScheduleCrypto::find($id);
            $buttons[] = [
                ['text' => '🛠️ Manual', 'callback_data' => "manual_management_{$schedule->id}"],
                ['text' => '🎯 Close at TP', 'callback_data' => "close_specific_tp_template_{$schedule->id}"],
            ];
            if($schedule->status != "running"){
                $buttons[] = [['text' => '💸 Partial', 'callback_data' => "partial_profit_select_templates_$id"]];
            }

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📝 Let's set up your {$schedule->type} trade tracking.</b>

                What's your strategy for taking profits?
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        // close tp 
        else if($type == "close_specific_tp"){
            $id = $data["id"];
            $schedule = ScheduleCrypto::find($id);
            $buttons = [];
            if(!empty($schedule->take_profit1)){
                $buttons[] = [['text' => '🎯 TP1', 'callback_data' => "close_specific_tp_type_{$id}_1"]];
            }
            if(!empty($schedule->take_profit2)){
                $buttons[] = [['text' => '🎯 TP2', 'callback_data' => "close_specific_tp_type_{$id}_2"]];
            }
            if(!empty($schedule->take_profit3)){
                $buttons[] = [['text' => '🎯 TP3', 'callback_data' => "close_specific_tp_type_{$id}_3"]];
            }
            if(!empty($schedule->take_profit4)){
                $buttons[] = [['text' => '🎯 TP4', 'callback_data' => "close_specific_tp_type_{$id}_4"]];
            }
            if(!empty($schedule->take_profit5)){
                $buttons[] = [['text' => '🎯 TP5', 'callback_data' => "close_specific_tp_type_{$id}_5"]];
            }
            if(!empty($schedule->take_profit6)){
                $buttons[] = [['text' => '🎯 TP6', 'callback_data' => "close_specific_tp_type_{$id}_6"]];
            }
            if(!empty($schedule->take_profit7)){
                $buttons[] = [['text' => '🎯 TP7', 'callback_data' => "close_specific_tp_type_{$id}_7"]];
            }
            if(!empty($schedule->take_profit8)){
                $buttons[] = [['text' => '🎯 TP8', 'callback_data' => "close_specific_tp_type_{$id}_8"]];
            }
            if(!empty($schedule->take_profit9)){
                $buttons[] = [['text' => '🎯 TP9', 'callback_data' => "close_specific_tp_type_{$id}_9"]];
            }
            if(!empty($schedule->take_profit10)){
                $buttons[] = [['text' => '🎯 TP10', 'callback_data' => "close_specific_tp_type_{$id}_10"]];
            }

            $flatButtons = [];
            foreach ($buttons as $btnRow) {
                // $btnRow is [button], so take first element
                $flatButtons[] = $btnRow[0];
            }
            $allButtons = array_chunk($flatButtons, 3);

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📍 At which Take Profit level would you like to close your entire position?</b>
                
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $allButtons
                ])
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        // questions 
        else if($type == "trade_volume_question_amount"){
            $id = $data["id"];
            $trade = ScheduleCrypto::find($id);

            // real trade 
            $getRisk = moneyManagmentGetRisk($chatId, true);
            $moneyManagementStatus = $getRisk["money_management_status"];

            if(!isset($data["reduce"])){
                if($moneyManagementStatus){
                    $this->telegramMessageType("money_management_position_size_confirmation", $chatId, ["id" => $id]);
                    return;
                }
            }

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>💰 What's the margin of your trade?</b>

                📌 Please choose the type of amount you'll use for this trade — for example, in coins (e.g., BTC, ETH) or in USDT.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💵 USDT', 'callback_data' => "trade_volume_question_amount_usdt_{$id}"],
                            ['text' => '🪙 Coins', 'callback_data' => "trade_volume_question_amount_coin_{$id}"],
                        ]
                    ]
                ])
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        else if($type == "trade_volume_question_amount_usdt"){
            $id = $data["id"];

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>💰 What's the Initial Margin of your trade?</b>

                <b>Please enter either:</b>
                - The amount in USDT (e.g., 100)
                EOT,
                'parse_mode' => 'HTML',
                // 'inline_keyboard' => [
                //     [
                //         ['text' => '💵 USDT', 'callback_data' => "trade_volume_question_amount_usdt_{$id}"],
                //         ['text' => '🪙 Coins', 'callback_data' => "trade_volume_question_amount_coin_{$id}"],
                //     ]
                // ]
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

            $user->state = "trade_volume_question_amount_usdt_$id";
            $user->save();
        }
        else if($type == "trade_volume_question_amount_coin"){
            $id = $data["id"];
            $trade = ScheduleCrypto::find($id);
            $coin = str_replace("USDT", "", $trade->instruments);

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>💰 What's the Initial Margin of your trade?</b>

                <b>Please enter either:</b>
                - Number of coins (e.g., 0.5 {$coin})
                EOT,
                'parse_mode' => 'HTML'
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

            $user->state = "trade_volume_question_amount_coin_$id";
            $user->save();
        }

        /*
        =====================
        SignalShot Risk Management
        =====================
        */
        else if($type == "money_management_position_size_confirmation"){
            $id = $data["id"]; 
            $trade = ScheduleCrypto::find($id);
            $jsonInfo = Cache::get("money_management_{$chatId}");

            // wallet balance
            $walletBalance = $trade->market === "bybit" ? $jsonInfo["bybit_wallet_balance"] : $jsonInfo["binance_wallet_balance"];
            $walletBalance = $jsonInfo["money_management_type"] === "demo" ? $jsonInfo["demo_wallet_balance"] : $walletBalance;
            
            if(empty($walletBalance)){
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>Wallet balance is 0</b>
                    EOT,
                    'parse_mode' => 'HTML'
                ]);
            }

            $demoAvailableBalance = $jsonInfo["demo_available_balance"];
            $risk = ($walletBalance/100) * $jsonInfo["percentage"];
         
            $riskPercentage = $jsonInfo["percentage"];

            $accountMaxExposure = $jsonInfo["max_exposure"];
            $riskTradeLimit = $jsonInfo["trade_limit"];
            $riskDailyLoss = $jsonInfo["daily_loss"];
            $stopTrades = $jsonInfo["stop_trades"];
            // status 
            $accountMaxExposureStatus = $jsonInfo["max_exposure_status"];
            $riskTradeLimitStatus = $jsonInfo["trade_limit_status"];
            $riskDailyLossStatus = $jsonInfo["daily_loss_status"];
            $stopTradesStatus = $jsonInfo["stop_trades_status"];

            $moneyManagementStatus = $jsonInfo["money_management_status"];

            $stopLossCalculation = calculateFuturesProfit($trade, null, $trade->stop_loss);
            $lossPercentage = $stopLossCalculation["breakdown"][0]["percentage_gain"];

            /*
            ========================
            Actual Size
            ========================
            */
            if($trade->tp_mode === "LONG"){
                $stopLossDistance = ($trade->entry_target - $trade->stop_loss) / $trade->entry_target*100;
            }else{
                $stopLossDistance = ($trade->stop_loss - $trade->entry_target) / $trade->entry_target*100;
            }
            $stopLossDistance = $stopLossDistance/100;

            $positionSizeAmount = $risk/$stopLossDistance;
            $positionSize = number_format($positionSizeAmount, 2, ".", "");
            $positionSizeWithoutLeverage = number_format($positionSizeAmount/$trade->leverage, 2, ".", "");
            
            // check demo  
            if($demoAvailableBalance < $positionSizeWithoutLeverage && $trade->type === "demo"){
                $positionSizeWithoutLeverage = $risk;
            }

            // calculating 
            $qty = formatNumberFlexible((($positionSizeWithoutLeverage*$trade->leverage) / $trade->entry_target), $trade->qty_step);
            $actualPositionSize = formatNumberFlexible($qty * $trade->entry_target, 2);
            $actualUSDT = formatNumberFlexible($actualPositionSize / $trade->leverage, 2);
            $coin = str_replace("USDT", "", $trade->instruments);


            // ======= max expose use ========
            $allSchedules = ScheduleCrypto::whereIn('status', ['running', 'waiting'])->orderBy('id', 'desc')->get();
            $schedules = $allSchedules->where('chat_id', $chatId)->sortByDesc('id');

            $useTrade = count($schedules);
            $useExpose = 0;
            foreach ($schedules as $schedule) {
                $useExpose = $useExpose + $schedule->position_size_usdt;
            }
            $useExposeP = formatNumberFlexible(($useExpose / $walletBalance) * 100, 2);

            $thisTradeExoposure = formatNumberFlexible(($actualUSDT / $walletBalance) * 100, 2);
            $useExposeWouldBe = formatNumberFlexible($thisTradeExoposure + $useExposeP, 2);

            // ======= daily loss ========
            $todaySchedules = ScheduleCrypto::where('status', 'closed')
            ->whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->get();
            $dailyExpose = 0;
            foreach ($todaySchedules as $schedule) {
                $dailyExpose = $dailyExpose + $schedule->actual_profit_loss;
            }
            $dailyExposePercentage = ($dailyExpose / $walletBalance) * 100;
            
            // check max exposure
            if($accountMaxExposure < $useExposeWouldBe && $accountMaxExposureStatus){
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🛑 Risk Limit Exceeded</b>

                    <b>Cannot open this trade!</b>
                    <b>Current Exposure:</b> {$useExposeP}%
                    <b>This Trade Risk:</b> {$thisTradeExoposure}%
                    <b>Total Would Be:</b> {$useExposeWouldBe}% (exceeds {$accountMaxExposure}% limit)

                    <b>Options:</b>
                    1. Close existing positions
                    2. Reduce position size
                    3. Wait for positions to close
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 View Positions', 'callback_data' => "my_signals"],
                                ['text' => '⬇️ Reduce Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                            ],
                            [
                                ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                            ]
                        ]
                    ])
                ]);
            }
            // check trade limit
            else if($riskTradeLimit <= $useTrade && $riskTradeLimitStatus){
                $useTradeWouldBe = $useTrade + 1;

                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🛑 Trade Limit Exceeded</b>

                    <b>Cannot open this trade!</b>
                    <b>Current Trade:</b> {$useTrade}
                    <b>Total Would Be:</b> {$useTradeWouldBe} (exceeds {$riskTradeLimit} limit)

                    <b>Options:</b>
                    1. Close existing positions
                    2. Wait for positions to close
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 View Positions', 'callback_data' => "my_signals"],
                                ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                            ]
                        ]
                    ])
                ]);
            }
            // daily expose 
            else if($riskDailyLoss < $dailyExposePercentage && $riskDailyLossStatus){
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🛑 Daily Risk Limit Exceeded</b>

                    <b>Cannot open this trade!</b>
                    <b>Current Risk Limit:</b> {$riskDailyLoss}%
                    <b>Current Loss:</b> {$dailyExposePercentage}%

                    <b>Options:</b>
                    1. Close existing positions
                    2. Wait for positions to close
                    3. Increase daily loss limit
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 View Positions', 'callback_data' => "my_signals"],
                                ['text' => '⬇️ Reduce Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                            ],
                            [
                                ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                            ]
                        ]
                    ])
                ]);
            }
            // success  
            else{
                // check the invent 
                if($actualUSDT < 1){
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>You cannot open a trade with 0 margin. Please increase your risk in order to open this trade.</b>
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                ],
                                [
                                    ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                ]
                            ]
                        ])
                    ]);
                }else{
                    // check if exposure is more then 30% ? 
                    if($thisTradeExoposure > 30){
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>📊 {$trade->instruments} | {$trade->tp_mode}</b>
                            <b>Entry:</b> {$trade->entry_target}
                            <b>Stop Loss:</b> {$trade->stop_loss} ({$lossPercentage}%)

                            <b>💰 Money Management Active</b>
                            <b>Risk per Trade:</b> {$riskPercentage}% ({$risk} USDT)
                            <b>Actual Margin:</b> {$actualUSDT} USDT 
                            <b>Calculated Position Size:</b> {$actualPositionSize} USDT ({$qty} {$coin})
                            <b>Risk for this Trade:</b> {$thisTradeExoposure}% ({$actualUSDT} USDT)

                            ⚠️ High Exposure Warning
                            ✅ Risk within limits
                            ❌ Exposure above recommended 30%

                            Proceed with this position?
                            EOT,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '✅ Start Tracking', 'callback_data' => "start_tracking_{$id}"],
                                        ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                    ],
                                    [
                                        ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                    ]
                                ]
                            ])
                        ]);
                        return;
                    }

                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📊 {$trade->instruments} | {$trade->tp_mode}</b>
                        <b>Entry:</b> {$trade->entry_target}
                        <b>Stop Loss:</b> {$trade->stop_loss} ({$lossPercentage}%)

                        <b>💰 Money Management Active</b>
                        <b>Risk per Trade:</b> {$riskPercentage}% ({$risk} USDT)
                        <b>Actual Margin:</b> {$actualUSDT} USDT 
                        <b>Calculated Position Size:</b> {$actualPositionSize} USDT ({$qty} {$coin})
                        <b>Risk for this Trade:</b> {$riskThisTradePercentage}% ({$actualUSDT} USDT)

                        <b>✅ This trade fits your risk profile</b>
                        <b>Current Exposure after trade:</b> {$useExposeWouldBe}%

                        Proceed with this position?
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '✅ Start Tracking', 'callback_data' => "start_tracking_{$id}"],
                                    ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                ],
                                [
                                    ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                ]
                            ]
                        ])
                    ]);
                    
                    $trade->risk_amount = $actualUSDT;
                    $trade->risk_percentage = $riskThisTradePercentage;
                    $trade->position_size_usdt = $actualUSDT;
                    $trade->qty = $qty;
                    $trade->save();
                }
            }

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

            $user->state = null;
            $user->save();
        }
        else if($type == "money_management_position_size_confirmation_by_amount"){
            $id = $data["id"];
            $trade = ScheduleCrypto::find($id);
            $amount = $data["amount"];

            $jsonInfo = Cache::get("money_management_{$chatId}");

            // wallet balance
            $walletBalance = $trade->market === "bybit" ? $jsonInfo["bybit_wallet_balance"] : $jsonInfo["binance_wallet_balance"];
            $walletBalance = $jsonInfo["money_management_type"] === "demo" ? $jsonInfo["demo_wallet_balance"] : $walletBalance;
            
            $demoAvailableBalance = $jsonInfo["demo_available_balance"];

            $risk = ($walletBalance/100) * $jsonInfo["percentage"];
            $riskPercentage = ($amount/$walletBalance)*100;   //$jsonInfo["percentage"];

            $accountMaxExposure = $jsonInfo["max_exposure"];
            $riskTradeLimit = $jsonInfo["trade_limit"];
            $riskDailyLoss = $jsonInfo["daily_loss"];
            $stopTrades = $jsonInfo["stop_trades"];
            // status 
            $accountMaxExposureStatus = $jsonInfo["max_exposure_status"];
            $riskTradeLimitStatus = $jsonInfo["trade_limit_status"];
            $riskDailyLossStatus = $jsonInfo["daily_loss_status"];
            $stopTradesStatus = $jsonInfo["stop_trades_status"];

            $moneyManagementStatus = $jsonInfo["money_management_status"];

            $stopLossCalculation = calculateFuturesProfit($trade, null, $trade->stop_loss);
            $lossPercentage = $stopLossCalculation["breakdown"][0]["percentage_gain"];

            /*
            ========================
            Actual Size
            ========================
            */
            $qty = formatNumberFlexible((($amount*$trade->leverage) / $trade->entry_target), $trade->qty_step);
            $actualPositionSize = formatNumberFlexible($qty * $trade->entry_target, 2);
            $actualUSDT = formatNumberFlexible($actualPositionSize / $trade->leverage, 2);
            $coin = str_replace("USDT", "", $trade->instruments);

            // ======= max expose use ========
            $actualUSDT = $amount;
            $allSchedules = ScheduleCrypto::whereIn('status', ['running', 'waiting'])->orderBy('id', 'desc')->get();
            $schedules = $allSchedules->where('chat_id', $chatId)->sortByDesc('id');

            $useTrade = count($schedules);
            $useExpose = 0;
            foreach ($schedules as $schedule) {
                $useExpose = $useExpose + $schedule->position_size_usdt;
            }
            $useExposeP = formatNumberFlexible(($useExpose / $walletBalance) * 100, 2);

            $thisTradeExoposure = formatNumberFlexible(($actualUSDT / $walletBalance) * 100, 2);
            $useExposeWouldBe = formatNumberFlexible($thisTradeExoposure + $useExposeP, 2);

            // daily loss 
            $todaySchedules = ScheduleCrypto::where('status', 'closed')
            ->whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->get();
            $dailyExpose = 0;
            foreach ($todaySchedules as $schedule) {
                $dailyExpose = $dailyExpose + $schedule->actual_profit_loss;
            }
            $dailyExposePercentage = ($dailyExpose / $walletBalance) * 100;

            // check max exposure
            if($accountMaxExposure < $useExposeWouldBe && $accountMaxExposureStatus){
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🛑 Risk Limit Exceeded</b>

                    <b>Cannot open this trade!</b>
                    <b>Current Exposure:</b> {$useExposeP}%
                    <b>This Trade Risk:</b> {$thisTradeExoposure}%
                    <b>Total Would Be:</b> {$useExposeWouldBe}% (exceeds {$accountMaxExposure}% limit)

                    <b>Options:</b>
                    1. Close existing positions
                    2. Reduce position size
                    3. Wait for positions to close
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 View Positions', 'callback_data' => "my_signals"],
                                ['text' => '⬇️ Reduce Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                            ],
                            [
                                ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                            ]
                        ]
                    ])
                ]);
            }
            // check trade limit
            else if($riskTradeLimit <= $useTrade && $riskTradeLimitStatus){
                $useTradeWouldBe = $useTrade + 1;

                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🛑 Trade Limit Exceeded</b>

                    <b>Cannot open this trade!</b>
                    <b>Current Trade:</b> {$useTrade}
                    <b>Total Would Be:</b> {$useTradeWouldBe} (exceeds {$riskTradeLimit} limit)

                    <b>Options:</b>
                    1. Close existing positions
                    2. Wait for positions to close
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 View Positions', 'callback_data' => "my_signals"],
                                ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                            ]
                        ]
                    ])
                ]);
            }
            // daily expose 
            else if($riskDailyLoss < $dailyExposePercentage && $riskDailyLossStatus){
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>🛑 Daily Risk Limit Exceeded</b>

                    <b>Cannot open this trade!</b>
                    <b>Current Risk Limit:</b> {$riskDailyLoss}%
                    <b>Current Loss:</b> {$dailyExposePercentage}%

                    <b>Options:</b>
                    1. Close existing positions
                    2. Wait for positions to close
                    3. Increase daily loss limit
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 View Positions', 'callback_data' => "my_signals"],
                                ['text' => '⬇️ Reduce Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                            ],
                            [
                                ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                            ]
                        ]
                    ])
                ]);
            }
            // success  
            else{
                // check if exposure is more then 30% ? 
                if($thisTradeExoposure > 30){
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>📊 {$trade->instruments} | {$trade->tp_mode}</b>
                        <b>Entry:</b> {$trade->entry_target}
                        <b>Stop Loss:</b> {$trade->stop_loss} ({$lossPercentage}%)

                        <b>💰 Money Management Active</b>
                        <b>Risk per Trade:</b> {$riskPercentage}% ({$risk} USDT)
                        <b>Actual Margin:</b> {$actualUSDT} USDT 
                        <b>Calculated Position Size:</b> {$actualPositionSize} USDT ({$qty} {$coin})
                        <b>Risk for this Trade:</b> {$thisTradeExoposure}% ({$actualUSDT} USDT)

                        ⚠️ High Exposure Warning
                        ✅ Risk within limits
                        ❌ Exposure above recommended 30%

                        Proceed with this position?
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '✅ Start Tracking', 'callback_data' => "start_tracking_{$id}"],
                                    ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                ],
                                [
                                    ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                ]
                            ]
                        ])
                    ]);
                    return;
                }
                

                // check the invent 
                if($actualUSDT < 1){
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>You cannot open a trade with 0 margin. Please increase your risk in order to open this trade.</b>
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                ],
                                [
                                    ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                ]
                            ]
                        ])
                    ]);
                }else{
                    // check demo  
                    if($demoAvailableBalance < $actualUSDT && $trade->type === "demo"){
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>Your trade size is increased more than your available balance. Please decrease your position size to open this trade.</b>
                            EOT,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                    ],
                                    [
                                        ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                    ]
                                ]
                            ])
                        ]);
                    }else{
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => <<<EOT
                            <b>📊 {$trade->instruments} | {$trade->tp_mode}</b>
                            <b>Entry:</b> {$trade->entry_target}
                            <b>Stop Loss:</b> {$trade->stop_loss} ({$lossPercentage}%)

                            <b>💰 Money Management Active</b>
                            <b>Calculated Position Size:</b> {$actualPositionSize} USDT ({$qty} {$coin})
                            <b>With {$trade->leverage}X leverage:</b> {$actualUSDT} USDT margin

                            <b>✅ This trade fits your risk profile</b>
                            <b>Current Exposure after trade:</b> {$useExposeWouldBe}%

                            Proceed with this position?
                            EOT,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '✅ Start Tracking', 'callback_data' => "start_tracking_{$id}"],
                                        ['text' => '⚠️ Manual Size', 'callback_data' => "reduce_trade_volume_question_amount_$id"],
                                    ],
                                    [
                                        ['text' => '❌ Cancel', 'callback_data' => "main_menu"],
                                    ]
                                ]
                            ])
                        ]);

                        // risk for this trade 
                        $riskThisTradePercentage = formatNumberFlexible(($actualUSDT/$walletBalance)*100, 2);
                        $trade->risk_amount = $actualUSDT;
                        $trade->risk_percentage = $riskThisTradePercentage;
                        $trade->position_size_usdt = $actualUSDT;
                        $trade->qty = $qty;
                        $trade->save();
                    }
                }
            }

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

            $user->state = null;
            $user->save();
        }

        /*
        =====================
        EDIT TRADE  
        =====================
        */
        else if($type == "edit_signal_buttons"){
            $id = $data["id"]; 
            $schedule = ScheduleCrypto::find($id);
            
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✏️ Edit Signal Mode</b>

                Current signal configuration:
                <b>📊 {$schedule->instruments} | {$schedule->tp_mode} | {$schedule->leverage}X</b>
                <b>Entry:</b> {$schedule->entry_target}
                <b>Stop Loss:</b> {$schedule->stop_loss}

                What would you like to modify?

                Select an option below:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📍 Entry Type', 'callback_data' => "entry_type_edit_signal_{$id}"],
                            ['text' => '⚖️ Leverage', 'callback_data' => "update_trade_leverage_{$id}"],
                        ],
                        [
                            ['text' => '🛑 Stop Loss', 'callback_data' => "update_trade_stop_loss_{$id}"],
                            ['text' => '✅ Continue', 'callback_data' => "select_trade_mod_{$id}"],
                        ]
                    ]
                ])
            ]);
           
        }
        else if($type == "edit_signal_entry_type"){
            $id = $data["id"];
            $schedule = ScheduleCrypto::find($id);

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📍 Entry Type Selection</b>

                <b>Current:</b> {$schedule->entry_target}

                Select your preferred entry type:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '⚡ Market Entry', 'callback_data' => "update_trade_market_entry_{$schedule->id}"],
                            ['text' => '📊 Limit Order', 'callback_data' => "update_trade_entry_point_{$schedule->id}"],
                        ],
                        [
                            ['text' => '❌ Back', 'callback_data' => "update_trade_back_{$schedule->id}"],
                        ]
                    ]
                ])
            ]);
        }

        /*
        =====================
            PARTIAL PROFIT  
        =====================
        */
        else if($type == "partial_profit_select_templates"){
            $id = $data["id"];
            $schedule = ScheduleCrypto::find($id);

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>✅ Select your partial profit strategy: </b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📂 My Templates', 'callback_data' => "partial_profit_my_templates_{$id}"],
                            ['text' => '➕ Create New', 'callback_data' => "partial_profit_new_templates_$id"],
                        ]
                    ]
                ])
            ]);

            // messages 
            if($schedule->status == "pending"){
                // add new ids  
                $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                $trackSignalMsgIds[] = $msgresInfo->message_id;
                Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
            }else{
                // add new ids  
                $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                $trackSignalMsgIds[] = $msgresInfo->message_id;
                Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
            }
        }
        else if($type == "partial_profit_my_templates"){
            $id = $data["id"];
            $schedule = ScheduleCrypto::find($id);

            $templates = PartialProfitTemplate::where("user_id", $chatId)->latest()->get();
            // If No Active Trades
            if(count($templates) < 1){
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>📋 You don't have any templates.</b>

                    Create a template to get started.
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '➕ Create New', 'callback_data' => "partial_profit_new_templates_$id"],
                            ]
                        ]
                    ])
                ]);

                // messages 
                if($schedule->status == "pending"){
                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }else{
                    // add new ids  
                    $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
                }
            }


            foreach ($templates as $key => $value) {
                // check the status 
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>{$value->name}</b>

                    ✅ Partial profit strategy:
                    TP1: {$value->tp1}%
                    TP2: {$value->tp2}%
                    TP3: {$value->tp3}%
                    TP4: {$value->tp4}%
                    TP5: {$value->tp5}%
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Select', 'callback_data' => "partial_profit_templates_select_{$value->id}_{$id}"],
                                ['text' => '🗑️ Remove', 'callback_data' => "partial_profit_templates_delete_{$value->id}"],
                            ]
                        ]
                    ])
                ]);

                // messages 
                if($schedule->status == "pending"){
                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }else{
                    // add new ids  
                    $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
                }
            }
        }
        else if($type == "partial_profit_new_templates"){
            $id = $data["id"];
            $percentage = $data["percentage"];
            $schedule = ScheduleCrypto::find($id);

            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Please specify the percentage to close at TP{$data['tp']}:</b>
                
                Available partials percentage: {$percentage}%

                (Click a button or type a custom percentage directly)

                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '10%', 'callback_data' => "take_partial_profits_10_$id"],
                            ['text' => '20%', 'callback_data' => "take_partial_profits_20_$id"],
                            ['text' => '25%', 'callback_data' => "take_partial_profits_25_$id"],
                        ],
                        [
                            ['text' => '50%', 'callback_data' => "take_partial_profits_50_$id"],
                            ['text' => '75%', 'callback_data' => "take_partial_profits_75_$id"],
                            ['text' => '100%', 'callback_data' => "take_partial_profits_100_$id"],
                        ]
                    ]
                ])
            ]);

            // messages 
            if($schedule->status == "pending"){
                // add new ids  
                $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                $trackSignalMsgIds[] = $msgresInfo->message_id;
                Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
            }else{
                // add new ids  
                $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                $trackSignalMsgIds[] = $msgresInfo->message_id;
                Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
            }
        }
        else if($type == "partial_profit_strategy_set"){
            $id = $data["id"];
            $schedule = ScheduleCrypto::find($id);

            if(!is_null($schedule)){
                $tp1 = is_null($schedule->partial_profits_tp1) ? 0 : $schedule->partial_profits_tp1;
                $tp2 = is_null($schedule->partial_profits_tp2) ? 0 : $schedule->partial_profits_tp2;
                $tp3 = is_null($schedule->partial_profits_tp3) ? 0 : $schedule->partial_profits_tp3;
                $tp4 = is_null($schedule->partial_profits_tp4) ? 0 : $schedule->partial_profits_tp4;
                $tp5 = is_null($schedule->partial_profits_tp5) ? 0 : $schedule->partial_profits_tp5;

                // check the status  
                $buttons = [];
                if($schedule->status == "pending"){
                    $buttons[] = [
                        ['text' => '✅ Confirm', 'callback_data' => "confirm_partial_profit_strategy_$id"],
                        ['text' => '✏️ Edit', 'callback_data' => "edit_partial_profit_strategy_$id"],
                    ];
                }else{
                    $buttons[] = [
                        ['text' => '⏭️ Skip', 'callback_data' => "partial_profit_skip_template_save_$id"],
                    ];
                }
                $buttons[] = [
                    ['text' => '📂 Save as Template', 'callback_data' => "partial_profit_save_template_name_$id"],
                ];
    
                $msgresInfo = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>✅ Partial profit strategy set:</b>
                    TP1: {$tp1}%
                    TP2: {$tp2}%
                    TP3: {$tp3}%
                    TP4: {$tp4}%
                    TP5: {$tp5}%
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $buttons
                    ])
                ]);
    
                // messages 
                if($schedule->status == "pending"){
                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }else{
                    // add new ids  
                    $trackSignalMsgIds = Cache::get('my_signal_update_partial_profit_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('my_signal_update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
                }
            }
        }

        /*
        =====================
            History 
        =====================
        */
        else if($type == "history"){
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                📜 Please select an option to view your trade history details:
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📥 Excel: Real', 'callback_data' => 'history_trades_type_real'],
                            ['text' => '📥 Excel: Demo', 'callback_data' => 'history_trades_type_demo'],
                        ],
                        [
                            ['text' => '📋 Excel: All', 'callback_data' => 'history_trades_type_all'],
                            ['text' => '📊 Statistics', 'callback_data' => 'statistics_trades_type_all'],
                        ],
                        [
                            ['text' => '📈 Quick List: Real', 'callback_data' => 'history_trades_quick_list_real'],
                            ['text' => '🧪 Quick List: Demo', 'callback_data' => 'history_trades_quick_list_demo'],
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "history_trades_type"){
            $type = $data['type'];

            // Step 1: Define select fields
            $selectFields = [
                'trade_id',
                'trade_entry',
                'instruments',
                'tp_mode',
                'trade_exit',
                'position_size_usdt',
                'leverage',
                'height_tp',
                'stop_loss',
                'actual_profit_loss',
                'status',
            ];

            // Step 2: Get data
            $schedules = ScheduleCrypto::where('chat_id', $chatId)
            ->where('status', '!=', 'pending')
            ->when($type !== 'all', fn($q) => $q->where('type', $type))
            ->select($selectFields)
            ->get();

            // Step 3: Transform data
            $transformed = $schedules->map(function ($item) {
                $positionSize = $item->position_size_usdt . 'USDT';
                $profit = $item->actual_profit_loss ?? '0.00';
                $profitWithUnit = 'USDT';

                // Attempt to parse date safely
                $format = 'd, m, y h:i a';
                try {
                    $entryRaw = $item->trade_entry ?: now()->format($format);
                    $exitRaw  = $item->trade_exit ?: now()->format($format);
                    $entryDate = Carbon::createFromFormat($format, $entryRaw);
                    $exitDate  = Carbon::createFromFormat($format, $exitRaw);
                
                    $diffInSeconds = $entryDate->diffInSeconds($exitDate);
                    $days = floor($diffInSeconds / 86400);
                    $hours = floor(($diffInSeconds % 86400) / 3600);
                    $minutes = floor(($diffInSeconds % 3600) / 60);
                
                    $durationParts = [];
                    if ($days) $durationParts[] = "{$days}d";
                    if ($hours) $durationParts[] = "{$hours}h";
                    if ($minutes) $durationParts[] = "{$minutes}m";
                
                    $duration = implode(' ', $durationParts);
                } catch (\Exception $e) {
                    $duration = 'Invalid date';
                }

                return collect([
                    'Trade ID'       => "'".$item->trade_id."'",
                    'Date/Time'      => $item->trade_entry,
                    'Trading Pair'   => $item->instruments,
                    'Direction'      => strtoupper($item->tp_mode),
                    // 'Exit Price'     => $item->trade_exit,
                    'Position Size'  => $positionSize,
                    'Leverage'       => $item->leverage,
                    'Take Profits'   => $item->height_tp,
                    'Stop Loss'      => $item->stop_loss,
                    'Profit/Loss'    => $profitWithUnit,
                    'Duration'       => $duration,
                    'Trade Status'   => strtoupper($item->status),
                ]);
            });

            // Step 4: Check for empty result
            if ($transformed->isEmpty()) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "❗ No trades found to export.",
                ]);
                return;
            }

            // Step 5: Prepare Excel data
            $headerRow = array_keys($transformed->first()->toArray());
            $dataRows = $transformed->map(fn($item) => array_values($item->toArray()))->toArray();
            $excelData = collect([$headerRow])->merge($dataRows);

            // Step 6: Store Excel file now()->format('ymdihs');
            $fileName = "SignalAlert_" . $type . "_" . now()->format('ymd_ihs') . ".xlsx";
            $tempPath = 'temp/' . $fileName;

            Excel::store(new class($excelData) implements \Maatwebsite\Excel\Concerns\FromCollection {
                public function __construct(public Collection $data) {}
                public function collection() {
                    return $this->data;
                }
            }, $tempPath, 'local');

            // Step 7: Send to Telegram
            $fileFullPath = storage_path("app/{$tempPath}");
            Telegram::sendDocument([
                'chat_id' => $chatId,
                'document' => fopen($fileFullPath, 'r'),
                'caption' => '📥 Here is your Excel file containing trade history.',
            ]);

            // Step 8: Clean up
            unlink($fileFullPath);
        }
        else if($type == "statistics"){
            $date = $data['date'];
            $dateUC = ucfirst($data['date']);

            // date text 
            $dateTxt = "";
            if($date == "7"){
                $dateTxt = "Last Week";
            }else if($date == "30"){
                $dateTxt = "Last Month";
            }else if($date == "90"){
                $dateTxt = "Last 3 Months";
            }else{
                $dateTxt = "All Time";
            }

            // Get schedules real
            $realSchedules = ScheduleCrypto::where('chat_id', $chatId)
            ->where("status", "closed")
            ->when("real" !== 'all', fn($q) => $q->where('type', "real"))
            ->when($date !== 'all', function ($q) use ($date) {
                $q->whereDay('created_at', $date);
            })
            ->get();

             // Get schedules demo
            $demoSchedules = ScheduleCrypto::where('chat_id', $chatId)
            ->where("status", "closed")
            ->when("demo" !== 'all', fn($q) => $q->where('type', "demo"))
            ->when($date !== 'all', function ($q) use ($date) {
                $q->whereDay('created_at', $date);
            })
            ->get();

            // Calculated values with safe division
            $safeDivide = fn($a, $b) => $b > 0 ? round(($a / $b) * 100, 2) : 0;

            /*
            ===================
            REAL TRADE
            ===================
            */
            // Count totals
            $realTotalTrade = $realSchedules->count();
            $realTotalProfitTrade = $realSchedules->where('actual_profit_loss', ">", 0)->count();
            $realTotalLossTrade = $realSchedules->where('actual_profit_loss', "<", 0)->count();
            // Corrected averages
            $realTotalAverageWinTrade   = $safeDivide($realTotalProfitTrade, $realTotalTrade);
            $realTotalAverageLossTrade  = $safeDivide($realTotalLossTrade, $realTotalTrade);
            $realTotalWinRateTrade      = $safeDivide($realTotalProfitTrade, $realTotalTrade);
            $realTotalActualProfit      = number_format($realSchedules->sum('actual_profit_loss'), 2);

            /*
            ===================
            DEMO TRADE
            ===================
            */
            // Count totals
            $demoTotalTrade = $demoSchedules->count();
            $demoTotalProfitTrade = $demoSchedules->where('actual_profit_loss', ">", 0)->count();
            $demoTotalLossTrade = $demoSchedules->where('actual_profit_loss', "<", 0)->count();
            // Corrected averages
            $demoTotalAverageWinTrade   = $safeDivide($demoTotalProfitTrade, $demoTotalTrade);
            $demoTotalAverageLossTrade  = $safeDivide($demoTotalLossTrade, $demoTotalTrade);
            $demoTotalWinRateTrade      = $safeDivide($demoTotalProfitTrade, $demoTotalTrade);
            $demoTotalActualProfit      = number_format($demoSchedules->sum('actual_profit_loss'), 2);

            // Filter 
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Trading Performance:</b>
                
                <b>📈 REAL TRADES</b>
                ⏱️ Time Period: {$dateTxt}
                📊 Total Trades: {$realTotalTrade}
                ✅ Profitable Trades: {$realTotalProfitTrade}
                ❌ Loss Trades: {$realTotalLossTrade}
                💰 Total Profit: {$realTotalActualProfit} USDT
                📈 Average Win: {$realTotalAverageWinTrade}%
                📉 Average Loss: {$realTotalAverageLossTrade}%
                ⚡ Win Rate: {$realTotalWinRateTrade}%

                <b>📉 DEMO TRADES</b>
                ⏱️ Time Period: {$dateTxt}
                📊 Total Trades: {$demoTotalTrade}
                ✅ Profitable Trades: {$demoTotalProfitTrade}
                ❌ Loss Trades: {$demoTotalLossTrade}
                💰 Total Profit: {$demoTotalActualProfit} USDT
                📈 Average Win: {$demoTotalAverageWinTrade}%
                📉 Average Loss: {$demoTotalAverageLossTrade}%
                ⚡ Win Rate: {$demoTotalWinRateTrade}%
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Provider Statistics', 'callback_data' => "provider_statistics"],
                        ],
                        [
                            ['text' => '🗓️ Change Time Period', 'callback_data' => "statistics_trades_change_time"],
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "provider_statistics"){

            $schedules = ScheduleCrypto::where('chat_id', $chatId)
            ->where('schedule_cryptos.status', "closed")
            ->join('signal_formats', 'signal_formats.id', '=', 'schedule_cryptos.provider_id')
            ->select(
                'signal_formats.format_name',
                DB::raw('COUNT(schedule_cryptos.id) as total_trade'),
                DB::raw("COUNT(CASE WHEN schedule_cryptos.actual_profit_loss > 0 THEN 1 END) as profit_count"),
                DB::raw("COUNT(CASE WHEN schedule_cryptos.actual_profit_loss < 0 THEN 1 END) as loss_count"),
                DB::raw('SUM(schedule_cryptos.actual_profit_loss) as total_profit_loss')
            )
            ->groupBy('signal_formats.format_name', 'schedule_cryptos.status') // Add status here to group by it
            ->get()
            ->map(function ($item) {
                $safeDivide = fn($a, $b) => $b > 0 ? round(($a / $b) * 100, 2) : 0;

                $item->total_profit_loss = number_format($item->total_profit_loss, 2);
                $item->win_rate = number_format($safeDivide($item->profit_count, $item->total_trade), 2);
                $item->loss_rate = number_format($safeDivide($item->loss_count, $item->total_trade), 2);

                return $item;
            })
            ->filter();  // Remove any null values from the result



            // [{"":"SignalShot","":11,"":3,"":7,"":"-1,108.55","":"27.27","":"63.64"}]

            $text = "";
            foreach ($schedules as $schedule) {
                $text .= <<<EOT
                \n\n<b>📈 {$schedule->format_name}</b>
                📊 Total Trades: {$schedule->total_trade}
                ✅ Profitable Trades: {$schedule->profit_count} ({$schedule->win_rate}%)
                ❌ Loss Trades: {$schedule->loss_count} ({$schedule->loss_rate}%)
                💰 Total Profit: {$schedule->total_profit_loss} USDT
                ⚡ Win Rate: {$schedule->win_rate}%
                EOT;
            }


            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 Provider Performance Analysis</b>
                ⏱️ Time Period: All Time{$text}
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 Back to Statistics', 'callback_data' => "statistics_trades_type_all"],
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "statistics_trades_change_time"){
            // Filter 
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "⏳ Choose a Time Period to Filter Your Trade Performance:",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📅 Last Week', 'callback_data' => "statistics_trades_type_7"],
                            ['text' => '🗓️ Last Month', 'callback_data' => "statistics_trades_type_30"],
                        ],
                        [
                            ['text' => '📊 Last 3 Months', 'callback_data' => "statistics_trades_type_90"],
                            ['text' => '🌐 All Time', 'callback_data' => "statistics_trades_type_all"],
                        ]
                    ]
                ])
            ]);
        }
        else if($type == "history_trades_quick_list"){
            $type = $data['type'];
            $messageText = "";

            // Daily Trading Summary
            $schedulesToday = ScheduleCrypto::where('chat_id', $chatId)
            ->where('type', $type)
            ->where('status', 'closed')
            ->whereDate('updated_at', Carbon::today())
            ->get();
            
            // Count totals
            $totalTradeToday = $schedulesToday->count();
            $totalProfitTradeToday = $schedulesToday->where('actual_profit_loss', ">", 0)->count();
            $totalLossTradeToday = $schedulesToday->where('actual_profit_loss', "<", 0)->count();
            // Calculated values with safe division
            $safeDivide = fn($a, $b) => $b > 0 ? round(($a / $b) * 100, 2) : 0;
            $totalAverageWinTradeToday   = $safeDivide($totalProfitTradeToday, $totalTradeToday);
            $totalAverageLossTradeToday  = $safeDivide($totalLossTradeToday, $totalTradeToday);


            $messageText .= "Daily Trading Summary\n";
            $messageText .= "💰 Total Trades: {$totalTradeToday}\n";
            $messageText .= "✅ Winning Trades: {$totalProfitTradeToday}\n";
            $messageText .= "🔴 Losing Trades: {$totalLossTradeToday}\n";
            $messageText .= "✅ Average Win: {$totalAverageWinTradeToday}%\n";
            $messageText .= "🔴 Average Loss: {$totalAverageLossTradeToday}%\n\n";
        
            // Individual Trades
            $schedules = ScheduleCrypto::where('chat_id', $chatId)
            ->where('type', $type)
            ->where('status', 'closed')
            ->orderBy('id', 'desc')
            ->take(20)
            ->get();

            $messages = [];
            foreach ($schedules as $index => $value) {
                $pair = $value->instruments ?? 'BTCUSDT';
                $profit = number_format($value->actual_profit_loss, 2) ?? '0';
                $tradeMode = $value->tp_mode ? strtoupper(substr($value->tp_mode, 0, 1)) : 'N';
                $date = $value->created_at ? Carbon::parse($value->created_at)->format('M j') : 'May 1';
        
                $profit_loss = $profit > 0 ? "✅" : "❌";
        
                $messages[] = "{$profit_loss} {$pair} {$tradeMode}: {$profit} USDT";
            }

            if ($schedules->isEmpty()) {
                $messages = ["No list found!"];
            }

            // Combine messages
            $messageText .= "Individual Trades:\n";
            $messageText .= implode("\n", $messages);

            // calculation 
            $totalTradeAll = $schedules->count();
            $totalProfitTradeAll = $schedules->where('actual_profit_loss', ">", 0)->count();
            $totalLossTradeAll = $schedules->where('actual_profit_loss', "<", 0)->count();
            // Calculated values with safe division
            $safeDivideAll = fn($a, $b) => $b > 0 ? round(($a / $b) * 100, 2) : 0;
            $totalAverageWinTradeAll   = $safeDivideAll($totalProfitTradeAll, $totalTradeAll);
            $totalAverageLossTradeAll  = $safeDivideAll($totalLossTradeAll, $totalTradeAll);

            $messageText .= "\n\nAll Time Analysis\n";
            $messageText .= "💰 Total Trades: {$totalTradeAll}\n";
            $messageText .= "✅ Winning Trades: {$totalProfitTradeAll}\n";
            $messageText .= "🔴 Losing Trades: {$totalLossTradeAll}\n";
            $messageText .= "✅ Average Win: {$totalAverageWinTradeAll}%\n";
            $messageText .= "🔴 Average Loss: {$totalAverageLossTradeAll}%\n\n";
        
            // Send to Telegram
            $typeUc = ucfirst($type);
            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "❌ Delete $typeUc Histories", 'callback_data' => "delete_trade_histories_$type"],
                        ],
                    ]
                ])
            ]);
        
            // Track sent message ID
            $messageIds = [];
            $messageIds[] = $response->getMessageId();
        
            // Delete old messages
            $allTelegramMsgIds = Cache::get('quick_lists_message_ids', []);
            if (!empty($allTelegramMsgIds)) {
                foreach ($allTelegramMsgIds as $messageId) {
                    try {
                        Telegram::deleteMessage([
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                        ]);
                    } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                        Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: 5003");
                    }
                }
            }
        
            // Save new message ID
            Cache::forget('quick_lists_message_ids');
            Cache::forever('quick_lists_message_ids', $messageIds);
        }
       /*
        =====================
            Notifications   
        =====================
        */
        else if($type == "trade_report"){
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>💰 Please provide your actual results from this trade</b>

                <b>Please enter:</b>
                - Loss USDT:
                EOT,
                'parse_mode' => 'HTML',
            ]);
        }
        
    }
    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function validateNewTrade($data, $market, $chatId, $strategy = null)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $text = trim($data['message']['text'] ?? '');

        // license check  
        $license = licenseCheck($chatId);
        if(!$license["status"]){
            $this->telegramMessageType($license["type"], $chatId);
            return "ok";
        }

        // Send "Checking..." message
        $connectingMsg = Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Connecting...',
        ]);

        // user type 
        $userType = $license["user_type"];

        // 🧹 Clean input
        $caption = $data['message']['caption'] ?? null;
        $text = empty($text) ? $caption : $text;
        $text = trim($text);

        $explodes = explode("\n", $text);

        $signalFormats = SignalFormat::orderBy("short", "asc")->where("status", "active");
        if (isset($data['message']['forward_origin'])) {
            $origin = $data['message']['forward_origin'];
            $id = $origin['sender_user']['id'] ?? $origin['chat']['id'] ?? null;
            $forwardID = isset($id) ? abs($id) : null;

            if ($forwardID !== null) {
                $formats = SignalFormat::where("group_id", $forwardID)->where("status", "active")->get();
                if ($formats->count() > 0) {
                    $signalFormats = $formats;
                } else {
                    $signalFormats = $signalFormats->get();
                }
            } else {
                $signalFormats = $signalFormats->get();
            }
        } else {
            $signalFormats = $signalFormats->get();
        }
        
        $signalDetails = [
            'provider_name' => null,
            'base' => null,
            'quote' => null,
            'tpMode' => null,
            'leverage' => null,
            'entryTarget' => null,
            'stopLoss' => null,
            'tp1' => null,
            'tp2' => null,
            'tp3' => null,
            'tp4' => null,
            'tp5' => null,
            'tp6' => null,
            'tp7' => null,
            'tp8' => null,
            'tp9' => null,
            'tp10' => null,
        ];
        
        foreach ($signalFormats as $signal) {
            foreach ($explodes as $line) {
                // provider name 
                $signalDetails["provider_name"] = $signal->format_name;
                $signalDetails["provider_id"] = $signal->id;
                $line = trim($line);
                
                try {
                    eval($signal->format_formula);
                } catch (\Throwable $th) {
                    // Log::info($th);
                }
            }

            // Log::info([
            //     "format" => $signalDetails,
            //     "id" => $signal->id
            // ]);

            // details 
            $provider_name = strtoupper($signalDetails["provider_name"]);
            $provider_id = strtoupper($signalDetails["provider_id"]);
            $base = strtoupper($signalDetails["base"]);
            $quote = strtoupper($signalDetails["quote"]);
            $tpMode = strtoupper($signalDetails["tpMode"]);
            $entryTarget = $signalDetails["entryTarget"];
            $leverage = !empty($signalDetails["leverage"]) ? $signalDetails["leverage"] : 1;
            $stopLoss = $signalDetails["stopLoss"];
            $tp1 = $signalDetails["tp1"];
            $tp2 = $signalDetails["tp2"];
            $tp3 = $signalDetails["tp3"];
            $tp4 = $signalDetails["tp4"];
            $tp5 = $signalDetails["tp5"]; 
            $tp6 = $signalDetails["tp6"]; 
            $tp7 = $signalDetails["tp7"]; 
            $tp8 = $signalDetails["tp8"]; 
            $tp9 = $signalDetails["tp9"]; 
            $tp10 = $signalDetails["tp10"];
            if (!empty($base) && !empty($quote) && !empty($tpMode) && !empty($entryTarget) && !empty($leverage) && !empty($stopLoss) && !empty($tp1)){
                break;
            }else{

                

                $signalDetails["provider_name"] = null;
                $signalDetails["base"] = null;
                $signalDetails["quote"] = null;
                $signalDetails["tpMode"] = null;
                $signalDetails["leverage"] = null;
                $signalDetails["entryTarget"] = null;
                $signalDetails["stopLoss"] = null;
                $signalDetails["tp1"] = null;
                $signalDetails["tp2"] = null;
                $signalDetails["tp3"] = null;
                $signalDetails["tp4"] = null;
                $signalDetails["tp5"] = null;
                $signalDetails["tp6"] = null;
                $signalDetails["tp7"] = null;
                $signalDetails["tp8"] = null;
                $signalDetails["tp9"] = null;
                $signalDetails["tp10"] = null;
            }
        }
        
        // Log::info($signalDetails);
        // Respond based on license validation result
        if (!isset($coinType)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "It seems the coin is missing and could not be located!",
            ]);

            // Delete "Checking..." message and the original message
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            return;
        }

        // price 
        $priceResponse = Http::get("https://thechainguard.com/instruments-price", [
            "symbol" => $coinType,
            "market" => $market
        ]);
        
        $price = $priceResponse->json();

        // Respond based on license validation result
        if (empty($price)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "It seems the {$coinType} coin is missing and could not be located!",
            ]);

            // Delete "Checking..." message and the original message
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);
            return;
        }

        // Now price 
        if($signalDetails["entryTarget"] === "NOW"){
            $entryTarget = $price;
        }

        if (!empty($provider_id) && !empty($base) && !empty($tpMode) && !empty($entryTarget) && !empty($leverage) && !empty($stopLoss) && !empty($tp1))
        {

            // get coin info
            $response = Http::get(config('services.api.ctypto_end_point')."/instruments-info", [
                "symbol" => $coinType,
                "market" => $market
            ]);
            $jsonResponse = $response->json();
            if (!$response->successful() || empty($jsonResponse["status"])) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text'    => "Something went wrong. Please try again later.",
                ]);
                return;
            }

            // stretegy data 
            $leverage_status = $strategy["leverage_status"] ?? false;
            $strategy_status = $strategy["strategy_status"] ?? 'inactive';
            $strategy_exchange = $strategy["strategy_exchange"] ?? "bybit";
            $strategy_mode = $strategy["strategy_mode"] ?? "LONG";
            $strategy_profit_strategy = $strategy["strategy_profit_strategy"] ?? "manual_management";
            $strategy_strategy_tp = $strategy["strategy_strategy_tp"] ?? 1;

            // leverage 
            if($leverage_status){
                $leverage = $strategy["leverage"];
            }

            // save data 
            ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->delete();
            $decimals = strlen(substr(strrchr($price, '.'), 1));
            $entryPrice = formatNumberFlexible($entryTarget, $decimals);

            $crypto = new ScheduleCrypto();
            $crypto->trade_id = now()->format('ymdihs');
            $crypto->chat_id = $chatId;
            $crypto->instruments = $coinType; 
            $crypto->entry_target = $entryPrice;
            $crypto->market = $strategy_status !== 'inactive' ? $strategy_exchange : $market;
            $crypto->leverage = $leverage;
            $crypto->tp_mode = $tpMode;
            $crypto->stop_loss = formatNumberFlexible($stopLoss, $decimals);
            $crypto->take_profit1 = !empty($tp1) ? formatNumberFlexible($tp1, $decimals) : null;
            $crypto->take_profit2 = !empty($tp2) ? formatNumberFlexible($tp2, $decimals) : null;
            $crypto->take_profit3 = !empty($tp3) ? formatNumberFlexible($tp3, $decimals) : null;
            $crypto->take_profit4 = !empty($tp4) ? formatNumberFlexible($tp4, $decimals) : null;
            $crypto->take_profit5 = !empty($tp5) ? formatNumberFlexible($tp5, $decimals) : null;
            $crypto->take_profit6 = !empty($tp6) ? formatNumberFlexible($tp6, $decimals) : null;
            $crypto->take_profit7 = !empty($tp7) ? formatNumberFlexible($tp7, $decimals) : null;
            $crypto->take_profit8 = !empty($tp8) ? formatNumberFlexible($tp8, $decimals) : null;
            $crypto->take_profit9 = !empty($tp9) ? formatNumberFlexible($tp9, $decimals) : null;
            $crypto->take_profit10 = !empty($tp10) ? formatNumberFlexible($tp10, $decimals) : null;
            $crypto->provider_id = $provider_id;
            $crypto->qty_step = $jsonResponse["count"];
            $crypto->status = 'pending';
            $crypto->height_price = $price;

            // partial stretegy   
            if($strategy_status !== 'inactive'){
                $crypto->type = $strategy_mode;
                $crypto->specific_tp = $strategy_strategy_tp;
                $crypto->profit_strategy = $strategy_profit_strategy;

                if(isset($strategy["strategy_strategy_partial"])){
                    $strategy_strategy_partial = $strategy["strategy_strategy_partial"];
                    $strategy_tp1 = $strategy_strategy_partial["tp1"] ?? null;
                    $strategy_tp2 = $strategy_strategy_partial["tp2"] ?? null;
                    $strategy_tp3 = $strategy_strategy_partial["tp3"] ?? null;
                    $strategy_tp4 = $strategy_strategy_partial["tp4"] ?? null;
                    $strategy_tp5 = $strategy_strategy_partial["tp5"] ?? null;

                    $crypto->partial_profits_tp1 = $strategy_tp1;
                    $crypto->partial_profits_tp2 = $strategy_tp2;
                    $crypto->partial_profits_tp3 = $strategy_tp3;
                    $crypto->partial_profits_tp4 = $strategy_tp4;
                    $crypto->partial_profits_tp5 = $strategy_tp5;
                }
            }

            $crypto->save();
            
            $market = ucfirst($market);

            // ✅ Collect TP values dynamically 
            $TPs = [];
            for ($i = 1; $i <= 10; $i++) {
                $prop = "take_profit{$i}";
                $tpVar = $crypto[$prop] ?? null;
                if (!empty($tpVar)) {
                    $TPs[] = $tpVar;
                }
            }

            // ✅ Calculate TP gains
            $tpsCalculation = [];
            foreach ($TPs as $tp) {
                $priceDifference = $tpMode === "LONG" ? $tp - $crypto->entry_target : $crypto->entry_target - $tp;
                $percentageChange = $priceDifference / $crypto->entry_target;
                $percentageGain = $percentageChange * 100 * $leverage;
                $tpsCalculation[] = formatNumberFlexible($percentageGain, 2);
            }

            // ✅ Generate TP display blocks
            $tpDisplay = '';
            foreach ($TPs as $index => $tpValue) {
                $gain = $tpsCalculation[$index];
                $formattedGain = ($gain > 0 ? '+' : '') . $gain . '%';
                $tpNum = $index + 1;
                $tpDisplay .= "\n<b>🎯TP{$tpNum}: {$tpValue} ({$formattedGain})</b>";
            }

            // ✅ Calculate SL percentage loss
            $lossCalculation = 0;
            $lossResult = calculateFuturesProfit($crypto, null, $stopLoss);
            if (isset($lossResult["breakdown"][0]["percentage_gain"])) {
                $lossCalculation = $lossResult["breakdown"][0]["percentage_gain"];
            }

            // Delete "Checking..." message and the original message
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            // ✅ Prepare
            $messageResponseDetechData = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 I've received your signal for $coinType</b> 

                <b>Provider:</b> $provider_name  
                <b>Type:</b> $tpMode  
                <b>Leverage:</b> {$leverage}X  
                <b>Entry:</b> {$crypto->entry_target} 
                <b>Stop Loss:</b> {$crypto->stop_loss} ({$lossCalculation}%)
                <b>Current Price:</b> {$price} 

                <b>Take Profit Levels:</b>{$tpDisplay}

                Is this a real trade you're taking or just for tracking/demo purposes?
                EOT,
                'parse_mode' => 'HTML'
            ]);

            // check strategy 
            if(isset($strategy["strategy_status"]) && $strategy["strategy_status"] == "passive"){
                $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $crypto->id]);
                return;
            }else if(isset($strategy["strategy_status"]) && $strategy["strategy_status"] == "active"){
                $getRisk = moneyManagmentGetRisk($chatId, true);
                $moneyManagementStatus = $getRisk["money_management_status"];
                if($moneyManagementStatus){
                    // Wallet balance
                    $walletBalance = $crypto->market === "bybit" ? $getRisk["bybit_wallet_balance"] : $getRisk["binance_wallet_balance"];
                    $walletBalance = $getRisk["money_management_type"] === "demo" ? $getRisk["demo_wallet_balance"] : $walletBalance;

                   
                    $risk = ($walletBalance/100) * $getRisk["percentage"];

                   
                    if ($walletBalance <= 0) {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => '<b>Wallet balance is 0</b>',
                            'parse_mode' => 'HTML'
                        ]);
                    }

                    // Risk & stop-loss
                    $risk = $walletBalance * ($getRisk['percentage'] ?? 0) / 100;
                    $entry = max((float)$crypto->entry_target, 1e-9);
                    $leverage = max((float)$crypto->leverage, 1);

                    $stopLossDistance = abs($crypto->entry_target - $crypto->stop_loss) / $entry;
                    $stopLossDistance = max($stopLossDistance, 1e-12); // prevent division by 0

                    // Position sizing
                    $posAmount = $risk / $stopLossDistance;
                    $posWithoutLev = $posAmount / $leverage;

                    // Demo cap
                    if ($crypto->type === 'demo' && ($getRisk['demo_available_balance'] ?? 0) < $posWithoutLev) {
                        $posWithoutLev = $risk;
                    }

                    // Qty & notional
                    $qty = formatNumberFlexible(($posWithoutLev * $leverage) / $entry, $crypto->qty_step);
                    $actualPositionSize = formatNumberFlexible($qty * $entry, 2);
                    $actualUSDT = formatNumberFlexible($actualPositionSize / $leverage, 2);

                    $crypto->position_size_usdt = $actualUSDT;
                    $crypto->qty = $qty;
                    $crypto->save();

                    if (($tpMode === "LONG" && $price < $entryPrice) || ($tpMode === "SHORT" && $price > $entryPrice)) {
                        $crypto->last_alert = "Market Entry";
                        $crypto->save();
                    }

                    $this->startTracking($crypto->id);
                }else{
                    $this->telegramMessageType("trade_volume_question_amount", $chatId, ["id" => $crypto->id]);
                }
                
                return;
            }

            // check market price and entry price $entryPrice $price
            if ($tpMode === "LONG" && $price < $entryPrice) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⚠️ <b>Warning:</b> The current market price ({$price}) is below your entry price ({$entryPrice}) for a LONG position. Please ensure we can enter at the market price, which is {$price}$",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '⚡ Market Entry', 'callback_data' => "update_trade_market_entry_{$crypto->id}"],
                                ['text' => '📊 Limit Order', 'callback_data' => "update_trade_entry_point_{$crypto->id}"],
                            ]
                        ]
                    ])
                ]);
                return;
            } elseif ($tpMode === "SHORT" && $price > $entryPrice) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⚠️ <b>Warning:</b> The current market price ({$price}) is above your entry price ({$entryPrice}) for a SHORT position. Please ensure we can enter at the market price, which is {$price}$",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '⚡ Market Entry', 'callback_data' => "update_trade_market_entry_{$crypto->id}"],
                                ['text' => '📊 Limit Order', 'callback_data' => "update_trade_entry_point_{$crypto->id}"],
                            ]
                        ]
                    ])
                ]);
                return;
            }

            // Confirm message 
            $msgresSelectType = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>📊 You selected:</b> <code>$market</code>

                Is this a real trade you're taking or just for tracking/demo purposes.
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '💰 Real Trade', 'callback_data' => "trade_type_real_{$crypto->id}"],
                            ['text' => '🎮 Demo Only', 'callback_data' => "trade_type_demo_{$crypto->id}"],
                        ],
                        [
                            ['text' => '✏️ Edit Signal', 'callback_data' => "edit_signal_{$crypto->id}"],
                        ]
                    ]
                ])
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresSelectType->getMessageId();
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
        else{
            if($userType === "0demo"){
                $msgresNotUnrecognized = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
                    <b>ℹ️ Signal Format Information</b>
                    Demo mode is limited to tracking signals from SignalShot only.

                    For the best experience with this signal and to track signals from any provider, please consider upgrading to premium.
                    EOT,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🔁 Try Another Signal', 'callback_data' => 'try_another_signal']
                            ],
                            [
                                ['text' => '🚀 Upgrade to Premium', 'callback_data' => 'upgrade_premium']
                            ],
                            [
                                ['text' => '🏠 Main Menu', 'callback_data' => 'main_menu']
                            ]
                        ]
                    ])
                ]);
            }else{
                if(!empty($provider_id)){
                    $provider = SignalFormat::find($provider_id);

                    // ✅ Generate TP display blocks
                    if(empty($tp1)){
                        $tpDisplay = "Unavailable ❌";
                    }else{
                        $TPs = [
                            $tp1,
                            $tp2,
                            $tp3,
                            $tp4,
                            $tp5,
                            $tp6,
                            $tp7,
                            $tp8,
                            $tp9,
                            $tp10,
                        ];
                        $tpDisplay = '';
                        foreach ($TPs as $index => $tpValue) {
                            $tpNum = $index + 1;
                            if(!empty($tpValue)){
                                $tpDisplay .= "\n<b>🎯TP{$tpNum}: {$tpValue}</b>";
                            }
                        }
                    }
                    

                    // coin type 
                    $coinType = "<b>📊 I've received your signal for {$coinType}</b>";
                    if(empty($coinType)){
                        $coinType = "We were unable to find your coin.";
                    }

                    // type 
                    if(empty($tpMode)){
                        $tpMode = "Unavailable ❌";
                    }

                    // leverage 
                    if(empty($leverage)){
                        $leverage = "Unavailable ❌";
                    }else{
                        $leverage = $leverage."X";
                    }

                    // entry 
                    if(empty($entryTarget)){
                        $entryTarget = "Unavailable ❌";
                    }

                    // stop loss 
                    if(empty($stopLoss)){
                        $stopLoss = "Unavailable ❌";
                    }

                    // ✅ Prepare and send Telegram message
                    $msgresNotUnrecognized = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        
                        {$coinType}

                        <b>Provider:</b> {$provider_name}
                        <b>Type:</b> {$tpMode}  
                        <b>Leverage:</b> {$leverage}
                        <b>Entry:</b> {$entryTarget} 
                        <b>Stop Loss:</b> {$stopLoss}

                        <b>Take Profit Levels:</b>{$tpDisplay}

                        <b>If you think there was an error in validating your formats, please reach out to us.</b>
                        EOT,
                        'parse_mode' => 'HTML'
                    ]);
                }else{
                    $user->state = "new_signal_provider_detected";
                    $msgresNotUnrecognized = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => <<<EOT
                        <b>⚠️ New Signal Provider Detected</b>

                        This signal format is not yet in our database. To help us add support for this provider:
                        1. Please paste the Telegram link to this signal provider
                        2. Our team will integrate this format within 24 hours
                        3. You'll receive an email confirmation when it's ready

                        Thank you for helping us improve SignalManager!
                        EOT,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🔁 Try Another Signal', 'callback_data' => 'try_another_signal']
                                ],
                                [
                                    ['text' => '❌ Cancel', 'callback_data' => 'main_menu']
                                ]
                            ]
                        ])
                    ]);
                }
            }

            // Delete "Checking..." message and the original message
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresNotUnrecognized->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
    }

    //===========START TRACKING====================
    public function startTracking($id)
    {
        $schedule = ScheduleCrypto::find($id);
        $chatId = $schedule->chat_id;
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);

        $user->state = null;
        if(empty($schedule)){
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "Not found!",
            ]);
            return;
        }

        // if real and pen trade 
        if($schedule->type === "real" && $schedule->status === "pending"){
            // Send "Connecting..." message
            $connectingMsg = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            // Market entry 
            if($schedule->last_alert === "Market Entry"){
                // Bybit
                if($schedule->market === "bybit"){
                    $orderPlace = $this->bybitAPI->marketEntry($schedule);
                }
                
                // binance  
                else{
                    $orderPlace = $this->binanceAPI->createOrder($schedule, "market");
                }

                // Delete "Connecting..." 
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $connectingMsg->getMessageId(),
                ]);

                // false 
                if(!$orderPlace["status"]){
                    $erMsg = $this->minifyBybitError($orderPlace["msg"], $id);
                    if(!empty($erMsg)){
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => $erMsg,
                            'reply_markup' => json_encode([
                                'keyboard' => [
                                    ['➕ Track Signal', '📋 My Signals'],
                                    ['📊 History', '🔑 License'],
                                    ['🆘 Help']
                                ],
                                'resize_keyboard' => true,
                                'one_time_keyboard' => true
                            ])
                        ]);
                    }
                    return;
                }
            }

            // limit order 
            else{
                // Create order
                if($schedule->market === "bybit"){
                    $orderPlace = $this->bybitAPI->createOrder($schedule);
                }else{
                    $orderPlace = $this->binanceAPI->createOrder($schedule, "order");
                }

                // Delete "Connecting..." 
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $connectingMsg->getMessageId(),
                ]);

                // false 
                if(!$orderPlace["status"]){
                    $erMsg = $this->minifyBybitError($orderPlace["msg"], $id);
                    if(!empty($erMsg)){
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => $erMsg,
                            'reply_markup' => json_encode([
                                'keyboard' => [
                                    ['➕ Track Signal', '📋 My Signals'],
                                    ['📊 History', '🔑 License'],
                                    ['🆘 Help']
                                ],
                                'resize_keyboard' => true,
                                'one_time_keyboard' => true
                            ])
                        ]);
                    }
                    return;
                }
            }
        }

        // Market_Entry 
        if($schedule->last_alert === "Market Entry"){
            $priceResponse = Http::get("https://thechainguard.com/instruments-price", [
                "symbol" => $schedule->instruments,
                "market" => $schedule->market
            ]);
            $price = $priceResponse->json();

            $schedule->entry_target = $price;
            $schedule->save();

            // send notify 
            if($schedule->status === "pending"){
                $schedule->status = "running";
                $schedule->save();

                startWaitingTrade($schedule, $price);
            }
        }

        // if real and waiting trade 
        if($schedule->type === "real" && $schedule->status === "waiting"){
            // connecting ...
            $connectingMsg = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Connecting...',
            ]);

            // Closed and Create order
            if($schedule->market === "bybit"){
                $closedOrder = $this->bybitAPI->closeOrder($schedule);
                $orderPlace = $this->bybitAPI->createOrder($schedule);
            }else{

            }

            // Delete "Connecting..." 
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $connectingMsg->getMessageId(),
            ]);

            // false 
            if(!$closedOrder["status"]){
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $closedOrder["msg"],
                ]);

                return;
            }
            if(!$orderPlace["status"]){
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $orderPlace["msg"],
                ]);
            }
        }

        // TradeText  
        $tradeText = "✅ Congratulations! Your trade has been successfully updated.";
        if($schedule->status === "pending"){
            $schedule->status = "waiting"; 
            $schedule->save();
            
            $tradeText = "✅ Congratulations! Your trade is now being tracked.";
        }

        addTradeToCache($schedule);

        // send 
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $tradeText,
            'reply_markup' => json_encode([
                'keyboard' => [
                    ['➕ Track Signal', '📋 My Signals'],
                    ['📊 History', '🔑 License'],
                    ['🆘 Help']
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);

        // remove old message 
        $allTrackSignalMsgs = Cache::get('track_signal_message_ids_'.$user->id);
        if (!empty($allTrackSignalMsgs)) {
            foreach ($allTrackSignalMsgs as $messageId) {
                try {
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                    Log::warning("Failed to delete Telegram message ID: $messageId. Reason: " . $e->getMessage() . " | Code: 5001");
                }
            }
        }
        // Forget the cached message IDs after deleting
        Cache::forget('track_signal_message_ids_'.$user->id);
    }
    
    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function takePartialProfits($chatId, $text, $id)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $schedule = ScheduleCrypto::find($id);
        if(empty($schedule)){
            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Oops! Something went wrong. Try again with a new signal! Code: 411",
            ]);

            return "ok";
        }

        $text = (int)trim($text);
        if (is_int($text)) {
            $tpPoint = 1;

            if(is_null($schedule->partial_profits_tp1)){
                $total = $text;
                if($total > 100){
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%.",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

                    return response('ok');
                }else{
                    $schedule->partial_profits_tp1 = $text;
                    $schedule->save();

                    $tpPoint = 2;
                }
            }
            else if(is_null($schedule->partial_profits_tp2)){
                $total = (int)($schedule->partial_profits_tp1 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    return response('ok');
                }else{
                    $schedule->partial_profits_tp2 = $text;
                    $schedule->save();

                    $tpPoint = 3;
                }
            }
            else if(is_null($schedule->partial_profits_tp3)){
                $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    return response('ok');
                }else{
                    $schedule->partial_profits_tp3 = $text;
                    $schedule->save();

                    $tpPoint = 4;
                }
            }
            else if(is_null($schedule->partial_profits_tp4)){
                $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $schedule->partial_profits_tp3 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    return response('ok');
                }else{
                    $schedule->partial_profits_tp4 = $text;
                    $schedule->save();

                    $tpPoint = 5;
                }
            }
            else if(is_null($schedule->partial_profits_tp5)){
                $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $schedule->partial_profits_tp3 + $schedule->partial_profits_tp4 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }else{
                    // check  
                    if($total != 100){
                        $msgresInfo = Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => "You need to set up your partial profits to be 100% fulfilled.",
                        ]);

                        // add new ids  
                        $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                        $trackSignalMsgIds[] = $msgresInfo->message_id;
                        Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                        return "ok";
                    }

                    $schedule->partial_profits_tp5 = $text;
                    $schedule->save();
                    $this->telegramMessageType("partial_profit_strategy_set", $chatId, ["id" => $id]);
                }
                
                return response('ok');
            }

            // check total 100% or not  ? 
            $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $schedule->partial_profits_tp3 + $schedule->partial_profits_tp4 + $schedule->partial_profits_tp5);
            if($total == 100){
                $this->telegramMessageType("partial_profit_strategy_set", $chatId, ["id" => $schedule->id]);
            }else{
                $this->telegramMessageType("partial_profit_new_templates", $chatId, ["tp" => $tpPoint, "id" => $id, "percentage" => 100-$total]);
            }

        }else{
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Please enter a valid integer between 1 and 100 (e.g., 10, 100).",
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
        }
    }
    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function updatePartialProfits($chatId, $text, $id)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $schedule = ScheduleCrypto::find($id);

        if(empty($schedule)){
            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Oops! Something went wrong. Try again with a new signal! Code: 411.2",
            ]);

            return "ok";
        }

        $text = (int)trim($text);
        if (is_int($text)) {
            $tpPoint = 1;

            if(is_null($schedule->partial_profits_tp1)){
                $total = $text;
                if($total > 100){
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%.",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);

                    return response('ok');
                }else{
                    $schedule->partial_profits_tp1 = $text;
                    $schedule->save();

                    $tpPoint = 2;
                }
            }
            else if(is_null($schedule->partial_profits_tp2)){
                $total = (int)($schedule->partial_profits_tp1 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    return response('ok');
                }else{
                    $schedule->partial_profits_tp2 = $text;
                    $schedule->save();

                    $tpPoint = 3;
                }
            }
            else if(is_null($schedule->partial_profits_tp3)){
                $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    return response('ok');
                }else{
                    $schedule->partial_profits_tp3 = $text;
                    $schedule->save();

                    $tpPoint = 4;
                }
            }
            else if(is_null($schedule->partial_profits_tp4)){
                $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $schedule->partial_profits_tp3 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                    return response('ok');
                }else{
                    $schedule->partial_profits_tp4 = $text;
                    $schedule->save();

                    $tpPoint = 5;
                }
            }
            else if(is_null($schedule->partial_profits_tp5)){
                $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $schedule->partial_profits_tp3 + $schedule->partial_profits_tp4 + $text);
                if($total > 100){
                    $current = $total-$text;
                    // msg 
                    $msgresInfo = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Please adjust your total partial profits to be under 100%\nCurrent partial profits: $current%",
                    ]);

                    // add new ids  
                    $trackSignalMsgIds = Cache::get('track_signal_message_ids_'.$user->id);
                    $trackSignalMsgIds[] = $msgresInfo->message_id;
                    Cache::forever('track_signal_message_ids_'.$user->id, $trackSignalMsgIds);
                }else{
                    $schedule->partial_profits_tp5 = $text;
                    $schedule->save();
                }
                
                $this->telegramMessageType("partial_profit_strategy_set", $chatId, ["id" => $id]);
                return response('ok');
            }
            $total = (int)($schedule->partial_profits_tp1 + $schedule->partial_profits_tp2 + $schedule->partial_profits_tp3 + $schedule->partial_profits_tp4 + $schedule->partial_profits_tp5);
            if($total == 100){
                $this->telegramMessageType("partial_profit_strategy_set", $chatId, ["id" => $id]);
            }else{
                $this->telegramMessageType("update_trade_partial_profit", $chatId, ["tp" => $tpPoint, "id" => $id]);
            }

        }else{
            $msgresInfo = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Please enter a valid integer between 1 and 100 (e.g., 10, 100).",
            ]);

            // add new ids  
            $trackSignalMsgIds = Cache::get('update_partial_profit_message_ids_'.$user->id);
            $trackSignalMsgIds[] = $msgresInfo->message_id;
            Cache::forever('update_partial_profit_message_ids_'.$user->id, $trackSignalMsgIds);
        }
    }
    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function  tradeVolumeQuestionAmountUSDT($chatId, $text, $id)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $schedule = ScheduleCrypto::find($id);
        $investment = trim($text);
        if(empty($schedule)){
            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Oops! Something went wrong. Try again with a new signal! Code: 412",
            ]);

            return "ok";
        }

        if (!is_numeric($investment)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Enter proper numeric values, e.g., 10, 10.11, etc:</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🏠 Main Menu', 'callback_data' => "main_menu"],
                        ]
                    ]
                ])
            ]);
            return;
        }

        // check money management system 
        $data = Cache::get("money_management_{$chatId}");
        if ($data && $data["money_management_status"]) {
            $this->telegramMessageType("money_management_position_size_confirmation_by_amount", $chatId, ["id" => $id, "amount" => $text]);
            return;
        }

        $entryPrice = $schedule->entry_target;
        $leverage = $schedule->leverage;

        // order val 
        $qty = formatNumberFlexible((($investment*$leverage) / $entryPrice), $schedule->qty_step);
        $orderValue = formatNumberFlexible($qty * $entryPrice, 2);

        $actualUSDT = formatNumberFlexible($orderValue / $leverage, 2);
        $coin = str_replace("USDT", "", $schedule->instruments);
        $reqDecimals = number_format(1 / pow(10, $schedule->qty_step), $schedule->qty_step, '.', '');

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => <<<EOT
            <b>💰 Position Size Confirmation</b>

            <b>Requested:</b> {$investment} USDT 
            <b>Position Size with {$leverage}X leverage:</b> {$orderValue} USDT ({$qty} {$coin}) 
            <b>Initial Margin:</b> {$actualUSDT} USDT 

            ⚠️ Due to Bybit's minimum order requirements ({$reqDecimals} {$coin}) and {$schedule->qty_step}-decimal precision, your actual position size may differ slightly from your requested amount.
            EOT,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Start Tracking', 'callback_data' => "start_tracking_{$id}"],
                        ['text' => '✏️ Different Amount', 'callback_data' => "btn_trade_volume_question_amount_{$id}"],
                    ]
                ]
            ])
        ]);

        if($schedule->type === "demo"){
            $schedule->qty = $qty;
            $schedule->position_size_usdt = $actualUSDT;
        }else{
            $schedule->qty = $qty;
            $schedule->position_size_usdt = $investment;
        }
        $schedule->save();

        $user->state = null;
        $user->save();
    }
    public function  tradeVolumeQuestionAmountCOIN($chatId, $text, $id)
    {
        $investment = trim($text);
        $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
        $schedule = ScheduleCrypto::where("chat_id", $chatId)->where("status", "pending")->first();
        if(empty($schedule)){
            $user->state = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Oops! Something went wrong. Try again with a new signal! Code: 413",
            ]);

            return "ok";
        }

        if (!is_numeric($investment)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => <<<EOT
                <b>Enter proper numeric values, e.g., 10, 10.11, etc:</b>
                EOT,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🏠 Main Menu', 'callback_data' => "main_menu"],
                        ]
                    ]
                ])
            ]);
            return;
        }

        $entryPrice = $schedule->entry_target;
        $leverage = $schedule->leverage;

        // order val 
        $positionSize = formatNumberFlexible($investment * $entryPrice * $leverage, 2);
        $margin = formatNumberFlexible($investment * $entryPrice, 2);
        $coin = str_replace("USDT", "", $schedule->instruments);

        // check money management system 
        $data = Cache::get("money_management_{$chatId}");
        if ($data && $data["money_management_status"]) {
            $this->telegramMessageType("money_management_position_size_confirmation_by_amount", $chatId, ["id" => $id, "amount" => $margin]);
            return;
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => <<<EOT
            <b>💰 Position Size  Confirmation</b>

            <b>Requested:</b> ({$investment} {$coin})
            <b>Position Size with {$leverage}X leverage:</b> {$positionSize} USDT 
            <b>Initial Margin:</b> {$margin} USDT 
            EOT,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Start Tracking', 'callback_data' => "start_tracking_{$id}"],
                        ['text' => '✏️ Different Amount', 'callback_data' => "btn_trade_volume_question_amount_{$id}"],
                    ]
                ]
            ])
        ]);
       
        $schedule->position_size_usdt = $margin;
        $schedule->qty = $investment;
        $schedule->save();

        $user->state = null;
        $user->save();
    }
    //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    public function randomPrices($price)
    {
        // random 
        $abs = abs($price);
        if ($abs == 0) {
            return [0];
        }
        // Calculate step based on scale
        if ($abs < 1) {
            $step = pow(10, floor(log10($abs)) - 1);
        } else {
            $step = pow(10, floor(log10($abs)) - 1);
        }
        // prices
        $prices = "";
        for ($i = 0; $i < 3; $i++) {
            $offset = rand(-2, 2);
            $newPrice = $price + ($offset * $step);
            $newPrice = max($newPrice, 0);
            $prices .= round($newPrice, 10).", ";
        }

        return $prices;
    }

    // DeepSeek 
    public function deepSeek_SignalFormat($msg)
    {
        $apiUrl = "https://api.deepseek.com/v1/chat/completions";  // Common REST structure
        // OR
        // $apiUrl = "https://platform.deepseek.ai/api/v1/chat";   

        $apiKey = env('DEEPEEK_API_KEY');
        $systemPrompt = 
        '
            Should any data be missing, kindly ensure it is filled in through meticulous research on Google. It is crucial to avoid leaving any data incomplete, as this pertains to critical trading-related information.
            You are a data extraction assistant. The user will provide a response in various formats. Your task is to extract specific data and return it in the following JSON format:

            If the relevant data is found, return it as:

            {
                "status": true,
                "data": {
                    "base": null,
                    "quote": null,
                    "tpMode": null,
                    "leverage": null,
                    "entryTarget": null,
                    "stopLoss": null,
                    "tp1": null,
                    "tp2": null,
                    "tp3": null,
                    "tp4": null,
                    "tp5": null
                }
            }

            Ensure that only the JSON response is returned, with no additional explanations or text. Do not include the "```json" text.
        ';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->post($apiUrl, [
            'model'      => 'deepseek-chat',
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Here is user provided format: $msg"],
            ],
            'temperature' => 0.7,
        ]);

        if ($response->successful()) {
            $msg = isset($response->json()["choices"][0]["message"]["content"]) ? $response->json()["choices"][0]["message"]["content"] : null;
        }else{
            $msg = 'Failed to connect!';
        }

        return $msg;
    }

    public function OpenAI_SignalFormat($msg)
    {
        $apiKey = env('OPENAI_API_KEY');
        $systemPrompt = 
        '
            Should any data be missing, kindly ensure it is filled in through meticulous research on Google. It is crucial to avoid leaving any data incomplete, as this pertains to critical trading-related information.
            You are a data extraction assistant. The user will provide a response in various formats. Your task is to extract specific data and return it in the following JSON format:

            If the relevant data is found, return it as:

            {
                "status": true,
                "data": {
                    "base": null,
                    "quote": null,
                    "tpMode": null,
                    "leverage": null,
                    "entryTarget": null,
                    "stopLoss": null,
                    "tp1": null,
                    "tp2": null,
                    "tp3": null,
                    "tp4": null,
                    "tp5": null
                }
            }

            Ensure that only the JSON response is returned, with no additional explanations or text. Do not include the "```json" text.
        ';

       $response = Http::withToken($apiKey)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $msg
                ]
            ],
            'max_tokens' => 500
        ]);

        return;
        if ($response->successful()) {
            $msg = isset($response->json()["choices"][0]["message"]["content"]) ? $response->json()["choices"][0]["message"]["content"] : null;
        }else{
            $msg = 'Failed to connect!';
        }

        return $msg;
    }

    /*
    Resoarch 
    */
    public function crypto(Request $request)
    {
    }

    // bybit err 
    public function humanNumber($n) {
        $n = (float)$n;
        return number_format($n, 0, '.', ',');
    }
    public function minifyBybitError($msg, $id){
        return $msg;

        // $schedule = ScheduleCrypto::find($id);

        $msg = trim($msg);

        $takeProfit = '/TakeProfit:(\d+)\s+set for\s+(Buy|Sell)\s+position\s+should be\s+(higher|lower)\s+than\s+base_price:(\d+)\?\?LastPriceCode:\s*(\d+)/i';
        $marketEntry = '/^expect Falling, but trigger_price\[(\d+)\] >= current\[(\d+)\]\?MarkPrice$/i';

        if (preg_match($takeProfit, $msg, $m)) {
            [$full, $tp, $side, $cmp, $base, $code] = $m;
            return sprintf(
                'TP must be %s than base (%s). TP=%s, base=%s [code %s]',
                strtolower($cmp),
                ucfirst(strtolower($side)),
                $this->humanNumber($tp),
                $this->humanNumber($base),
                $code
            );
        }else if($marketEntry){
        }

        return $msg;
    }

    /*
    ===========================
    // Signal Shot  
    ===========================
    */
    public function SignalShot_NewSignal(Request $request)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $request->chat_id]);
        $user->state = "track_new_signal";
        $user->save();

        return response()->json(["status" => true]);
    }
}