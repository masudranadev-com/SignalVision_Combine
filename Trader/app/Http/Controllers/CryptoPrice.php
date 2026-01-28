<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CryptoPrice extends Controller
{
    public function crypto(Request $request)
    {
        $details = json_decode($request->cryptos);

        // $results = [
        //     'binance' => $this->getBinancePrices($details['binance'] ?? []),
        //     'bybit'   => $this->getBybitPrices($details['bybit'] ?? []),
        // ];

        $results = $this->getBybitPrices($details ?? []);

        return response()->json($results);
    }

    private function getBinancePrices(array $cryptos): array
    {
        $response = Http::get("https://fapi.binance.com/fapi/v1/ticker/price"); // https://api.binance.com/api/v3/ticker/price - SPOT

        if (!$response->successful()) {
            return [];
        }

        $data = collect($response->json())->mapWithKeys(function ($item) {
            return [$item['symbol'] => $item['price']];
        });

        return $data->only($cryptos)->toArray();
    }

    private function getBybitPrices(array $cryptos): array
    {
        $endpoint = "https://api.bybit.com/v5/market/tickers"; //"https://api-testnet.bybit.com/v5/market/tickers"; // 
        
        $response = Http::get($endpoint, [
            'category' => 'linear'//'spot',
        ]);

        if (!$response->successful() || !isset($response['result']['list'])) {
            return [];
        }

        $data = collect($response['result']['list'])->mapWithKeys(function ($item) {
            return [$item['symbol'] => $item['lastPrice']];
        });

        return $data->only($cryptos)->toArray();
    }
    
    
    // info 
    public function instrumentsPrice(Request $req)
    {
        $symbol = $req->symbol;

        if($req->market === "bybit"){
            $response = Http::get("https://api.bybit.com/v5/market/tickers", [
                "category" => "linear",
                "symbol" => $symbol
            ]);
            $price = $response["result"]["list"][0]["lastPrice"] ?? null;
        }else{
            $response = Http::get("https://fapi.binance.com/fapi/v2/ticker/price", [
                "symbol" => $symbol
            ]);

            \Log::info($response);

            $price = $response["price"] ?? null;
        }

        return $price;
    }
    public function instrumentsInfo(Request $req)
    {
        $symbol = $req->symbol;

        // if($req->market === "bybit"){
            $response = Http::get("https://api.bybit.com/v5/market/instruments-info", [
                "category" => "linear",
                "symbol" => $symbol
            ]);

            $qty = isset($response->json()["result"]["list"][0]["lotSizeFilter"]["qtyStep"]) ? $response->json()["result"]["list"][0]["lotSizeFilter"]["qtyStep"] : 0.001;

            $count = $this->countDecimals($qty);
        // }else{
        // }
        
        return response()->json([
            "status" => true,
            "count" => $count
        ]);
    }
    function countDecimals($number) {
        if (strpos($number, '.') !== false) {
            return strlen($number) - strpos($number, '.') - 1;
        }
        return 0;
    }
}
