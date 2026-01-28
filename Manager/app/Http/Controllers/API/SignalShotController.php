<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\Setting;
use App\Models\ScheduleCrypto;
use App\Models\PartialProfitTemplate;
use Illuminate\Support\Facades\Cache;

class SignalShotController extends Controller
{
    // activeRealPositions
    public function activeRealPositions(Request $req)
    {
        $chatId = $req->user_id;
        $market = $req->market;
        
        $allSchedules = Cache::remember("schedule_cryptos_running_waiting", now()->addHours(1), function () {
            return ScheduleCrypto::whereIn('status', ['running', 'waiting'])->orderBy('id', 'desc')->get();
        });
        $schedules = $allSchedules->where('chat_id', $chatId)->where('type', "real")->where('market', $market)->where('status', "running")->sortByDesc('id');

        $useTrade = count($schedules);
        $useExpose = 0;
        $useExposeAmount = 0;
        $lists = [];
        foreach ($schedules as $schedule) {
            // total 
            $useExpose = $useExpose + $schedule->risk_percentage;
            $useExposeAmount = $useExposeAmount + $schedule->risk_amount;

            $stopLossCalculation = calculateFuturesProfit($schedule, null, $schedule->stop_loss);
            $lossPercentage = $stopLossCalculation["breakdown"][0]["percentage_gain"];

            $lists[] = [
                "instruments" => $schedule->instruments,
                "tp_mode" => $schedule->tp_mode,
                "risk_amount" => $schedule->risk_amount,
                "risk_percentage" => $schedule->risk_percentage,
                "loss_ercentage" => $lossPercentage
            ];
        }

        // ✅ Step 4: Return success response
        return response()->json([
            'lists' => $lists,
            'current_exposure_percentage' => formatNumberFlexible($useExpose, 2),
            'current_exposure_amount' => formatNumberFlexible($useExposeAmount, 2),
        ]);
    }

    // activeDemoPositions
    public function activeDemoPositions(Request $req)
    {
        $chatId = $req->user_id;
        
        $allSchedules = Cache::remember("schedule_cryptos_running_waiting", now()->addHours(1), function () {
            return ScheduleCrypto::whereIn('status', ['running', 'waiting'])->orderBy('id', 'desc')->get();
        });
        $schedules = $allSchedules->where('chat_id', $chatId)->where('type', "demo")->where('status', "running")->sortByDesc('id');

        // price 
        $cryptoPrices = cryptoPrices();
        $useTrade = count($schedules);
        $PnL = 0;
        $useExpose = 0;
        $useExposeAmount = 0;
        // $availableBalance = 0;
        $lists = [];
        foreach ($schedules as $schedule) {
            // total 
            $useExpose = $useExpose + $schedule->risk_percentage;
            $useExposeAmount = $useExposeAmount + $schedule->risk_amount;

            $stopLossCalculation = calculateFuturesProfit($schedule, null, $schedule->stop_loss);
            $lossPercentage = $stopLossCalculation["breakdown"][0]["percentage_gain"];
            
            // P & L 
            $instrument = $schedule->instruments;
            $currentPrice = $cryptoPrices[$instrument] ?? 0;

            $entryPoint = $schedule->entry_target;
            $stopLoss = $schedule->stop_loss;
            $leverage = $schedule->leverage;
            $intInvestment = $schedule->position_size_usdt;

            // partial 
            if ($schedule->profit_strategy === "partial_profits") {
                $secureProfit = 0;
                $totalGain = 0;

                $partialProfits = [];
                for ($i = 1; $i <= 10; $i++) {
                    $tpField = "take_profit{$i}";
                    $tpPrice = $schedule->$tpField;
                    $val = $schedule->{"partial_profits_tp{$i}"} ?? null;
                    if (!is_null($val) && $val != 0) {
                        $partialProfits[$tpPrice] = $val;
                    }
                }

                $calculation = calculateFuturesProfit($schedule, $partialProfits, null);
                $totalPartialPercentage = 0;

                foreach ($calculation["breakdown"] as $index => $breakdown) {
                    if (($index + 1) <= $schedule->height_tp) {
                        $secureProfit += $breakdown["profit"];
                        $totalGain += $breakdown["percentage_gain"];
                        $totalPartialPercentage += $breakdown["partial"];
                    }
                }

                $remainingPercentage = 100 - $totalPartialPercentage;
                $remainingInvestment = ($remainingPercentage / 100) * $intInvestment;

                // profit 
                if ($schedule->tp_mode === "LONG") {
                    $priceDifference = $currentPrice - $entryPoint;
                } else { // SHORT
                    $priceDifference = $entryPoint - $currentPrice;
                }

                $percentageChange = $priceDifference / $entryPoint;
                $profit = $remainingInvestment * $percentageChange * $leverage;

                $finalResultAmount = $secureProfit + $profit;
            } else {
                $calculateProfit = calculateFuturesProfit($schedule, null, $currentPrice);
                $finalResultAmount = $calculateProfit["breakdown"][0]["profit"]; 
            }
            $PnL = $PnL + $finalResultAmount;

            $lists[] = [
                "instruments" => $schedule->instruments,
                "tp_mode" => $schedule->tp_mode,
                "risk_amount" => $schedule->risk_amount,
                "risk_percentage" => $schedule->risk_percentage,
                "loss_ercentage" => $lossPercentage,
                "current_pnl" => formatNumberFlexible($finalResultAmount, 2)
            ];


            // $availableBalance = $schedule->position_size_usdt + $finalResultAmount;
        }

        // ✅ Step 4: Return success response
        return response()->json([
            'lists' => $lists,
            'current_exposure_percentage' => formatNumberFlexible($useExpose, 2),
            'current_exposure_amount' => formatNumberFlexible($useExposeAmount, 2),
            // 'available_balance' => formatNumberFlexible($availableBalance, 2),
            "pnl" => formatNumberFlexible($PnL, 2),
        ]);
    }

    // reset money risk  
    public function riskManagementReset(Request $req)
    {
        $chatId = $req->user_id;
        moneyManagmentGetRisk($chatId, true);
    }

    // partial templates 
    public function partialTemplates(Request $req)
    {
        $templates = PartialProfitTemplate::where("user_id", $req->user_id)->get();

        return response()->json($templates);
    }
}
