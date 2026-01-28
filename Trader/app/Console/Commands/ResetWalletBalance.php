<?php

namespace App\Console\Commands;

use Illuminate\Http\Request;
use Illuminate\Console\Command;
use App\Http\Controllers\CryptoApiBybit;
use App\Models\TelegramUser;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\CryptoApiBinance;

class ResetWalletBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-wallet-balance'; // php artisan app:reset-wallet-balance

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = TelegramUser::where('money_management_status', "active")
                     ->get();

        foreach ($users as $key => $user) {
            try {
                if($user->money_management_type === "real"){
                    $response = Http::withHeaders([
                        'API-SECRET' => env("API_SECRET")
                    ])->post(env("SIGNAL_MANAGEMENT_END_POINT").'/api/signal-shot/get-crypto-wallet', [
                        "user_id" => $user->chat_id,
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
                        Telegram::sendMessage([
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
                        Telegram::sendMessage([
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
                    
                }else{
                    $available_balance = $user->money_management_demo_available_balance;
                    $wallet_balance = $user->money_management_demo_wallet_balance;
                    $pnl = number_format($available_balance - $wallet_balance, 2, '.', '');

                    $user = TelegramUser::find($user->id);
                    $margin_balance = number_format($available_balance, 2, '.', '');
                    $plText = $pnl > 0 ? "+$pnl" : $pnl;
                    $risk_percentage = $user["money_management_risk"];
                    $risk = number_format(($user["money_management_risk"]/100)*$margin_balance, 2, '.', '');

                    // send msg  
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => <<<EOT
                        <b>💰 Demo Money Management - Daily Reset System</b>

                        <b>Base Balance (Reset Daily): </b>$margin_balance USDT 🔒
                        <b>Last Reset:</b> Today at 00:00 UTC
                        <b>Next Reset:</b> Tomorrow at 00:00 UTC

                        <b>Current Wallet:</b> {$margin_balance} USDT ({$plText} today)
                        <b>Risk per Trade:</b> {$risk_percentage}% of {$margin_balance} = {$risk} USDT

                        All trades today use the {$margin_balance} base ✓
                        EOT,
                        'parse_mode' => 'HTML',
                    ]);

                    $user->money_management_demo_wallet_balance = $margin_balance;
                    $user->money_management_demo_available_balance = $margin_balance;
                    $user->save();
                }
                

            } catch (\Throwable $th) {
                \Log::info("Err CMD: $th");
            }
        }

    }
}
