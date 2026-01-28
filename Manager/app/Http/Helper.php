<?php
use WeStacks\TeleBot\TeleBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\ScheduleCrypto;
use App\Models\TelegramUser;
use App\Models\Subscription;
use Carbon\Carbon;
use \App\Http\Controllers\BybitAPIController;
use App\Events\TradeConfigEvent;

function combineCryptoPrices($details)
{
  $response = Http::get("https://thechainguard.com/crypto", [
      "cryptos" => json_encode($details) ?? [],
  ]);

  return $response->json();
}

function cryptoPrices()
{
  $response = Http::get("https://trader.signalvision.ai/prices");

  return $response->json();
}

function formatNumberFlexible($number, $decimals = 8) {
    $cleanNumber = str_replace(',', '', $number);
    $num = (float)$cleanNumber;
    $factor = pow(10, $decimals);
    $truncated = floor($num * $factor) / $factor;
    $formatted = number_format($truncated, $decimals, '.', '');
    return $formatted;
}

// Notifications 
function getTakeProfitTemplate($trade, $hitted_tp)
{
    $template = [
        'title' => '',
        'body' => '',
        'recommendation' => '',
        'buttons' => [
            [['text' => '❌ Close Trade', 'callback_data' => "close_trade_{$trade->id}"]],
        ],
    ];

    switch ($hitted_tp) {
        case 1:
            $template['title'] = "✅ TAKE PROFIT 1 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP1: [TP1] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP2]
            EOT;
            $template['recommendation'] = "Your trade is still being tracked for higher targets.";
            break;

        case 2:
            $template['title'] = "✅ TAKE PROFIT 2 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP2: [TP2] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP3]
            EOT;
            $template['recommendation'] = "Your trade is still being tracked for higher targets.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 3:
          $template['title'] = "✅ TAKE PROFIT 3 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP3: [TP3] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP4]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider moving your stop loss to break-even (entry point) to secure your trade profits.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 4:
            $template['title'] = "✅ TAKE PROFIT 4 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP4: [TP4] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP5]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider moving your stop loss to break-even (entry point) to secure your trade profits.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 5:
            $template['title'] = "🌟 TAKE PROFIT 5 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP5: [TP5] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 6:
            $template['title'] = "🌟 TAKE PROFIT 6 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP6: [TP6] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;
            
        case 7:
            $template['title'] = "🌟 TAKE PROFIT 7 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP7: [TP7] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 8:
            $template['title'] = "🌟 TAKE PROFIT 8 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP8: [TP8] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 9:
            $template['title'] = "🌟 TAKE PROFIT 9 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP9: [TP9] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 10:
            $template['title'] = "🌟 TAKE PROFIT 10 REACHED!";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP10: [TP10] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;
    }

    return $template;
}
function getTakeProfitReverseTemplate($trade, $hitted_tp)
{
    $template = [
        'title' => '',
        'body' => '',
        'recommendation' => '',
        'buttons' => [
            [['text' => '❌ Close Trade', 'callback_data' => "close_trade_{$trade->id}"]],
        ],
    ];

    switch ($hitted_tp) {
        case 1:
            $template['title'] = "🔴 Market Reversed to TP1";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP1: [TP1] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP2]
            EOT;
            $template['recommendation'] = "Your trade is still being tracked for higher targets.";
            break;

        case 2:
            $template['title'] = "🔴 Market Reversed to TP2";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP2: [TP2] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP3]
            EOT;
            $template['recommendation'] = "Your trade is still being tracked for higher targets.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 3:
          $template['title'] = "🔴 Market Reversed to TP3";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP3: [TP3] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP4]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider moving your stop loss to break-even (entry point) to secure your trade profits.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;
        case 4:
            $template['title'] = "🔴 Market Reversed to TP4";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP4: [TP4] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            Next Target: [TP5]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider moving your stop loss to break-even (entry point) to secure your trade profits.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 5:
            $template['title'] = "🔴 Market Reversed to TP5";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP5: [TP5] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 6:
            $template['title'] = "🔴 Market Reversed to TP6";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP6: [TP6] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 7:
            $template['title'] = "🔴 Market Reversed to TP7";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP7: [TP7] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 8:
            $template['title'] = "🔴 Market Reversed to TP8";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP8: [TP8] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 9:
            $template['title'] = "🔴 Market Reversed to TP9";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP9: [TP9] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;

        case 10:
            $template['title'] = "🔴 Market Reversed to TP10";
            $template['body'] = <<<EOT
            [PAIR-USDT] [LONG/SHORT]
            Entry: [Entry]
            TP10: [TP10] ← REACHED
            Current Price: [CURRENT_PRICE]
            Profit: [PROFIT]
            EOT;
            $template['recommendation'] = "🔒 RECOMMENDATION: Consider taking partial profits now and moving stop loss to your last TP level to lock in gains.";
            $template['buttons'][] = [['text' => '⚙️ Update Stop Loss', 'callback_data' => "update_trade_stop_loss_{$trade->id}"]];
            break;
    }

    return $template;
}
// profit 
function aiAdvisorTakeProfit($trade, $hitted_tp, $reverse, $current_price, $isFinalTrade)
{
    // Select the appropriate template based on $reverse flag
    $template = $reverse ? getTakeProfitTemplate($trade, $hitted_tp) : getTakeProfitReverseTemplate($trade, $hitted_tp);

    if (!$template) {
        return;
    }

    // Extract essential trade details
    $entryPrice = $trade->entry_target;
    $stopLossPrice = $trade->stop_loss;
    $tradeLeverage = $trade->leverage;

    // Collect all take profit levels dynamically
    $takeProfitTargets = [];
    for ($level = 1; $level <= 10; $level++) {
        $takeProfitTargets[$level] = $trade->{'take_profit' . $level} ?? 'N/A';
    }

    $inlineKeyboardButtons = $template['buttons'] ?? [];
    $messageTitle = "🎯 TARGET PRICE REACHED!";

    $activeTakeProfitProperty = 'take_profit' . $hitted_tp;
    $currentTakeProfitValue = $trade->$activeTakeProfitProperty ?? null;

    $profitCalculation = calculateFuturesProfit($trade, null, $current_price);
    $percentageGain = $profitCalculation["breakdown"][0]["percentage_gain"] ?? 0;
    $profitAmount = $profitCalculation["breakdown"][0]["profit"] ?? 0;
    $currencySymbol = "USDT";

    $securedProfitMessage = "";
    $totalSecuredProfit = 0;

    if ($trade->profit_strategy === "partial_profits") {
        $partialProfitAllocations = [];

        for ($level = 1; $level <= 10; $level++) {
            $partialProfitValue = $trade->{"partial_profits_tp{$level}"} ?? null;
            if (!is_null($partialProfitValue) && $partialProfitValue != 0) {
                $partialProfitAllocations[$takeProfitTargets[$level]] = $partialProfitValue;
            }
        }

        $detailedCalculation = calculateFuturesProfit($trade, $partialProfitAllocations, null);

        foreach ($detailedCalculation["breakdown"] as $index => $breakdownData) {
            $currentLevel = $index + 1;

            if (!is_null($trade->height_tp) && $trade->height_tp >= $currentLevel) {
                $totalSecuredProfit += $breakdownData["profit"] ?? 0;
            }

            if ($currentLevel == $hitted_tp) {
                $percentageGain = $breakdownData["percentage_gain"] ?? 0;
                $profitAmount = $breakdownData["profit"] ?? 0;
            }
        }

        if ($totalSecuredProfit > 0) {
            $securedProfitMessage = "<b>Total secured profit:</b> <code>" . formatNumberFlexible($totalSecuredProfit, 2) . " {$currencySymbol}</code>";
        }

        $messageTitle = $template['title'] ?? $messageTitle;
    }

    $finalTradeMessage = "";

    if (!empty($isFinalTrade)) {
        $finalTradeMessage = "<b>Your final trade is complete. We are closing your trade.</b>";
        $inlineKeyboardButtons = [];
    } else {
        $finalTradeMessage = $template['recommendation'] ?? '';
    }

    // Format profit and percentage for the notification
    $formattedProfit = formatNumberFlexible($profitAmount, 2);
    $formattedPercentage = formatNumberFlexible($percentageGain, 2);

    $formattedProfitMessage = sprintf(
        "%s%% (%s %s)",
        $percentageGain < 0 ? $formattedPercentage : "+$formattedPercentage",
        $profitAmount < 0 ? $formattedProfit : "+$formattedProfit",
        $currencySymbol
    );

    // Prepare placeholders and their replacements for the message template
    $templatePlaceholders = [
        '[PAIR-USDT]'    => $trade->instruments ?? 'PAIRUSDT',
        '[LONG/SHORT]'   => strtoupper($trade->tp_mode ?? 'LONG'),
        '[CURRENT_PRICE]'=> $current_price ?? 'N/A',
        '[PROFIT]'       => $formattedProfitMessage,
        '[Entry]'        => $entryPrice ?? 'N/A',
    ];

    for ($level = 1; $level <= 10; $level++) {
        $templatePlaceholders["[TP{$level}]"] = $takeProfitTargets[$level];
    }

    // Construct the final message text by replacing placeholders
    $finalMessage = $messageTitle . "\n" . strtr($template['body'], $templatePlaceholders);
    $finalMessage .= "\n\n{$securedProfitMessage}\n{$finalTradeMessage}";

    // Send the message to Telegram
    $messageResponse = Telegram::sendMessage([
        'chat_id' => $trade->chat_id,
        'text' => $finalMessage,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboardButtons]),
    ]);

    // Update the trade's actual profit/loss and save
    $trade->actual_profit_loss = formatNumberFlexible($profitAmount + $totalSecuredProfit, 2);
    $trade->save();

    // Cache key for stored Telegram message ID
    $telegramMessagesCacheKey = "signal_notification_ids_{$trade->id}";
    $previousMessageId = Cache::get($telegramMessagesCacheKey);
    if (!empty($previousMessageId)) {
        try {
            Telegram::deleteMessage([
                'chat_id' => $trade->chat_id,
                'message_id' => $previousMessageId,
            ]);
        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
            Log::warning("Failed to delete Telegram message ID: | Code: Cron Job");
        }
    }
    Cache::forget($telegramMessagesCacheKey);
    Cache::forever($telegramMessagesCacheKey, $messageResponse->getMessageId());

    // remove from node 
    if($trade->status === "closed"){
        // delete 
        event(new TradeConfigEvent("delete", $trade->id));
        return;
    }
}
// stop 
function aiAdvisorTakeLose($trade, $current_price)
{
    $entry_point = $trade->entry_target;
    $stop_loss = $current_price;
    $type = $trade->tp_mode;

    // int  
    $leverage = $trade->leverage;
    $intInvestment = $trade->position_size_usdt;
    $intInvestmentSign = "USDT";

    // profit 
    $profitCalculation = calculateFuturesProfit($trade, null, $current_price);
    $profitCount = $profitCalculation["breakdown"][0]["profit"];

    // partial 
    $secureProfit = 0;
    $totalGain = 0;
    if($trade->profit_strategy == "partial_profits"){
        $partialProfits = [];
        for ($i = 1; $i <= 10; $i++) {
            $tpField = "take_profit{$i}";
            $tpPrice = $trade->$tpField;

            $val = $trade->{"partial_profits_tp{$i}"} ?? null;
            if(!is_null($val)){
                $partialProfits[$tpPrice] = (!is_null($val) && $val != 0) ? $val : null;
            }
        }

        $calculation = calculateFuturesProfit($trade, $partialProfits, null);
        $totalPertialPercentage = 0;
        foreach ($calculation["breakdown"] as $index=>$breakdown) {
            $currentTp = $index + 1;

            if($currentTp <= $trade->height_tp){
                // profit  
                $gainPartial = $breakdown["percentage_gain"];
                $profitPartial = $breakdown["profit"];
                $secureProfit = $profitPartial + $secureProfit;
                $totalGain = $gainPartial + $totalGain;
                $totalPertialPercentage = $totalPertialPercentage + $breakdown["partial"];
            }
        }

        $reminingPertialPercentage = 100 - $totalPertialPercentage;
        $reminingInvestment = ($reminingPertialPercentage/100) * $intInvestment;

        // profit 
        if ($type === 'LONG') {
            $profit = (($stop_loss - $entry_point) / $entry_point) * $leverage * 100;
        } else { // SHORT
            $profit = (($entry_point - $stop_loss) / $entry_point) * $leverage * 100;
        }
        $profitCount = (($profit / 100) * $reminingInvestment);

        $finalResult = $profitCount + $secureProfit;
    }else{
        $finalResult = $profitCount;
    }

    $formattedFinalResult = formatNumberFlexible($finalResult, 2);
    
    if($formattedFinalResult < 1){
        $resultTxt = "The trade was closed with a loss.";
        $showingResult = $formattedFinalResult;

        $trade->trade_exit = date('d, m, y m:h a');
        $trade->actual_profit_loss = $formattedFinalResult;
        $trade->save();
    }else{
        $resultTxt = "The trade was closed with a win.";
        $showingResult = "+".$formattedFinalResult;

        $trade->trade_exit = date('d, m, y m:h a');
        $trade->actual_profit_loss = $formattedFinalResult;
        $trade->save();
    }

    Telegram::sendMessage([
        'chat_id' => $trade->chat_id,
        'text' => <<<EOT
        ❌ STOP LOSS HIT!

        {$trade->instruments} {$trade->tp_mode}
        Entry: {$entry_point}
        Investment: {$intInvestment} {$intInvestmentSign}
        Stop Loss: {$trade->stop_loss} ← REACHED
        Current Price: {$current_price}
        Result: {$showingResult} {$intInvestmentSign}

        {$resultTxt}
        {$trade->tp_mode} trade has been closed and moved to history.
        EOT,
        'parse_mode' => 'HTML',
    ]);

    removeTradeFromCache($trade->id);
}
// strat 
function startWaitingTrade($trade, $current_price)
{
    try {
        if($trade->status === "closed"){
            // delete 
            event(new TradeConfigEvent("delete", $trade->id));
            
            return;
        }

        // call real server
        if($trade->type === "real"){
            if($trade->market === "bybit"){
                $dataAPI = new BybitAPIController();
            }else{
                $dataAPI = new BinanceAPIController();
            }

            // partial 
            if($trade->profit_strategy === "partial_profits"){
                $dataAPI->createPartialOrder($trade);
            }
        }

        $mode = $trade->tp_mode;
        $entry_point = $trade->entry_target;
        $stop_loss = $trade->stop_loss;
        $tp1 = $trade->take_profit1;
        $tp2 = $trade->take_profit2;
        $tp3 = $trade->take_profit3;
        $tp4 = $trade->take_profit4;
        $tp5 = $trade->take_profit5;
        $tp6 = $trade->take_profit6;
        $tp7 = $trade->take_profit7;
        $tp8 = $trade->take_profit8;
        $tp9 = $trade->take_profit9;
        $tp10 = $trade->take_profit10;


        // TPs 
        $vTP1 = empty($tp1) ? "" : "\n<b>🎯TP1: {$tp1}</b>";
        $vTP2 = empty($tp2) ? "" : "\n<b>🎯TP2: {$tp2}</b>";
        $vTP3 = empty($tp3) ? "" : "\n<b>🎯TP3: {$tp3}</b>";
        $vTP4 = empty($tp4) ? "" : "\n<b>🎯TP4: {$tp4}</b>";
        $vTP5 = empty($tp5) ? "" : "\n<b>🎯TP5: {$tp5}</b>";
        $vTP6 = empty($tp6) ? "" : "\n<b>🎯TP6: {$tp6}</b>";
        $vTP7 = empty($tp7) ? "" : "\n<b>🎯TP7: {$tp7}</b>";
        $vTP8 = empty($tp8) ? "" : "\n<b>🎯TP8: {$tp8}</b>";
        $vTP9 = empty($tp9) ? "" : "\n<b>🎯TP9: {$tp9}</b>";
        $vTP10 = empty($tp10) ? "" : "\n<b>🎯TP10: {$tp10}</b>";

        $messageResponse = Telegram::sendMessage([
            'chat_id' => $trade->chat_id,
            'text' => <<<EOT
            <b>🎯 ENTRY POINT REACHED!</b>

            $trade->instruments $mode
            Entry: $entry_point ← REACHED

            Your signal is now active and being tracked.
            Current Price: $current_price
            Stop Loss: $stop_loss

            <b>Take Profit Targets:</b>{$vTP1}{$vTP2}{$vTP3}{$vTP4}{$vTP5}{$vTP6}{$vTP7}{$vTP8}{$vTP9}{$vTP10}

            I'll continue monitoring and alert you when any price targets are reached.
            EOT,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '❌ Close Trade', 'callback_data' => "close_trade_$trade->id"],
                    ]
                ]
            ])
        ]);

        $trade->trade_entry = date('Y-m-d H:i:s');
        $trade->save();
    } catch (\Throwable $th) {
        Log::info("Entry Notice: $th");
    }
}
// err errorNotifications
function errorNotifications($msg)
{
    return; 
    // Telegram::sendMessage([
    //     'chat_id' => '@SignalManager_ERROR',
    //     'text' => <<<EOT
    //     <b>❌ Error</b>
        
    //     {$msg}
    //     EOT,
    //     'parse_mode' => 'HTML'
    // ]);
}

// int  
function calculateFuturesProfit($trade, $tpLevels = null, $fixedTP = null) {
    // Extract values from trade object
    $entry = (float)$trade->entry_target;
    $leverage = (float)$trade->leverage;
    $investment = (float)$trade->position_size_usdt;
    $instrument = $trade->instruments;
    $intInvestmentSign = "USDT";
    $type = $trade->tp_mode;

    $results = [];
    $totalProfit = 0;
    $totalClosedInvestment = 0;
    $availableInvestment = $investment;

    // Case 1: Partial TP levels (array of targets and %s)
    if ($tpLevels && is_array($tpLevels)) {
        foreach ($tpLevels as $tp => $partialPercent) {
            $closedInvestment = $investment * ($partialPercent / 100);
            $targetPoint = (float)$tp;
            
            if ($type === "LONG") {
                $priceDifference = $targetPoint - $entry;
            } else { // SHORT
                $priceDifference = $entry - $targetPoint;
            }
            
            $percentageChange = $priceDifference / $entry;
            $percentageGain = $percentageChange * 100 * $leverage;
            $profit = $closedInvestment * $percentageChange * $leverage;

            $totalProfit += $profit;
            $totalClosedInvestment += $closedInvestment;
            $availableInvestment -= $closedInvestment;

            $results[] = [
                'tp' => $targetPoint,
                'partial' => formatNumberFlexible($partialPercent, 2),
                'closed_investment' => $closedInvestment,
                'available_investment' => $availableInvestment,
                'percentage_gain' => formatNumberFlexible($percentageGain, 2),
                'profit' => $profit
            ];
        }
    }

    // Case 2: Fixed TP target
    if (!is_null($fixedTP) && is_numeric($fixedTP)) {
        if ($type === "LONG") {
            $priceDifference = $fixedTP - $entry;
        } else { // SHORT
            $priceDifference = $entry - $fixedTP;
        }

        $percentageChange = $priceDifference / $entry;
        $percentageGain = $percentageChange * 100 * $leverage;
        $profit = $investment * $percentageChange * $leverage;


        $results[] = [
            'percentage_gain' => formatNumberFlexible($percentageGain, 2),
            'profit' => $profit
        ];

        $totalProfit = round($profit, 2);
        $totalClosedInvestment = round($investment, 2);
        $availableInvestment = 0.00;
    }

    return [
        'breakdown' => $results,
        'total_profit' => $totalProfit,
        'remaining_investment' => $availableInvestment,
        'closed_investment' => $totalClosedInvestment,
    ];
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
            "msg" => "Something went wrong. Please try again later."
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

    // check authentication 
    if (!isset($data['product_id']) || $data['product_id'] != 554) {
        return [
            "status" => false,
            "msg" => "Your license is invalid! Code: 202"
        ];
    }

    // Determine package type
    $package_type =  $data["variation_attributes"]['period'];

    // Format dates using Carbon
    $startDate = Carbon::parse($data['start_date'])->format('d-m-y h:i:s A');
    $nextDate = Carbon::parse($data['next_date'])->format('d-m-y h:i:s A');

    // Create or update Telegram user
    $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
    $user->max_signal = 10;
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
        "msg" => "License activated successfully."
    ];
}
function licenseCheck($chatId)
{
    $user = TelegramUser::firstOrCreate(['chat_id' => $chatId]);
    $userType = "demo";
    $now = Carbon::now();

    // Step 1: Check license status via external API
    $response = Http::withHeaders([
        'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
    ])->post(config('services.api.ctypto_end_point') . '/api/license/status', [
        "chat_id" => $chatId
    ]);

    if (!$response->successful()) {
        return [
            "status" => false,
            "type" => "license_connection_err",
        ];
    }

    // Step 2: Parse expiration from API
    $signalExpiredAt = $expired = Carbon::parse(trim($response['expired_in']));
    $diffInSecondsTrader = $now->diffInSeconds($signalExpiredAt, false);

    // Step 3: DEMO user logic
    if (is_null($user->expired_in)) {
        $signalCount = ScheduleCrypto::where("chat_id", $chatId)->count();
        if ($signalCount >= env("FREE_SIGNAL_LIMIT") && $diffInSecondsTrader <= 0) {
            return [
                "status" => false,
                "type" => "license_limit",
            ];
        }
    }

    // Step 4: REAL user logic
    else {
        $signalExpiredAt = $expired = Carbon::parse(trim($user->expired_in));
        $diffInHoursUser = $now->diffInHours($signalExpiredAt, false);
        $userType = "real";

        if ($diffInHoursUser <= 0 && $diffInSecondsTrader < 0) {
            return [
                "status" => false,
                "type" => "license_status"
            ];
        }

        $runningSignals = ScheduleCrypto::where("chat_id", $chatId)
        ->whereIn("status", ["running", "waiting"])
        ->count();

        if ($runningSignals > 9) {
            return [
                "status" => false,
                "type" => "license_premium_limit"
            ];
        }
    }

    // Step 5: All checks passed
    return [
        "status" => true,
        "user_type" => $userType
    ];
}

// cache  
function addTradeToCache($tradeData)
{
    $cacheKey = 'schedule_cryptos_running_waiting';
    $cachedTrades = Cache::get($cacheKey, []);
    $cachedTrades[] = $tradeData;
    Cache::put($cacheKey, $cachedTrades, now()->addHours(1));

    // add 
    if($tradeData->status != "pending"){
        event(new TradeConfigEvent("add", $tradeData));
    }
}
function removeTradeFromCache($tradeId)
{
    $stringID = (string)$tradeId;
    $cacheKey = 'schedule_cryptos_running_waiting';
    $cachedTrades = Cache::get($cacheKey);

    // If cache is empty or not a collection, do nothing
    if (empty($cachedTrades)) {
        return;
    }

    // Ensure it's a collection for safe chaining
    if (!($cachedTrades instanceof \Illuminate\Support\Collection)) {
        $cachedTrades = collect($cachedTrades);
    }

    // Filter out the trade by ID and reindex
    $updatedTrades = $cachedTrades->reject(fn($trade) => (string)$trade->id === $stringID)->values();

    // delete 
    event(new TradeConfigEvent("delete", $stringID));

    // Only update cache if a trade was removed
    if ($updatedTrades->count() !== $cachedTrades->count()) {
        Cache::put($cacheKey, $updatedTrades, now()->addHours(1));
    }
}
function updateTradeInCache($tradeData)
{
    $cacheKey = 'schedule_cryptos_running_waiting';
    $cachedTrades = Cache::get($cacheKey);
    if (!$cachedTrades) {
        return;
    }

    foreach ($cachedTrades as $key => $cachedTrade) {
        if ($cachedTrade->id === $tradeData->id) {
            $cachedTrades[$key] = $tradeData;
            break;
        }
    }
    Cache::put($cacheKey, $cachedTrades, now()->addHours(1));

    // delete 
    if($tradeData->status === "closed"){
        event(new TradeConfigEvent("delete", $tradeData->id));
    }

    // update demo balance 
    $getRisk = moneyManagmentGetRisk($tradeData->chat_id, false);
    $moneyManagementType = $getRisk["money_management_type"] ?? "demo";
    if($tradeData->type === "demo" && $tradeData->status === "closed" && $moneyManagementType === "demo"){
        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/money-management/demo-balance-update', [
            "chat_id" => $tradeData->chat_id,
            "value" => $tradeData->actual_profit_loss
        ]);
    }
}

// get risk manage 
function moneyManagmentGetRisk($chat_id, $new)
{
    if($new || empty($data)){
        // get data
        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/money-management/get-risk', [
            "chat_id" => $chat_id
        ]);
        $jsonInfo = $response->json();

        // add to cache 
        Cache::forever("money_management_{$chat_id}", $jsonInfo);
        return $jsonInfo;
    }else{
        $data = Cache::get("money_management_{$chat_id}");
        return $data;
    }
}