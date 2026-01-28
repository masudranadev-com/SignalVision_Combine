<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use Throwable;

class CryptoApiBybit extends Controller
{
    public function wallet($userId)
    {
        $user_id = (string) $userId;

        $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
        $apiSecretHeader = env('API_SECRET');

        $response = Http::withHeaders([
            'API-SECRET' => $apiSecretHeader,
        ])->get("{$endpoint}/api/signal-shot/get-crypto-wallet", [
            'user_id'    => $user_id,
            'type'       => 'bybit'
        ]);

        if (!$response->successful()) {
            return [
                'status' => false,
                'msg'    => 'Bybit API keys not configured for this user.',
                'hint'   => 'MissingKeys',
            ];
        }

        return $response->object();
    }

    public function position($userId)
    {
        $user_id = (string) $userId;

        $endpoint = rtrim(env('SIGNAL_MANAGEMENT_END_POINT'), '/');
        $apiSecretHeader = env('API_SECRET');

        $response = Http::withHeaders([
            'API-SECRET' => $apiSecretHeader,
        ])->get("{$endpoint}/api/signal-shot/get-crypto-positions", [
            'user_id'    => $user_id,
            'type'       => 'bybit'
        ]);

        if (!$response->successful()) {
            return [
                'status' => false,
                'msg'    => 'Bybit API keys not configured for this user.',
                'hint'   => 'MissingKeys',
            ];
        }

        return $response->object();
    }
}
