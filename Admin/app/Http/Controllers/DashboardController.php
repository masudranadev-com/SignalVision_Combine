<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch trades data
        $trades = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.manager_end_point') . '/api/admin/trades')->json();

        // Fetch recent users for display (5 users)
        $managerRes = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.manager_end_point') . '/api/admin/users', [
            "page" => 1,
            "per_page" => 5
        ])->json();

        $shotRes = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.shot_end_point') . '/api/admin/users', [
            "page" => 1,
            "per_page" => 5
        ])->json();

        // Fetch all users to get accurate total counts
        $allManagerRes = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.manager_end_point') . '/api/admin/users', [
            "page" => 1,
            "per_page" => 10000 // Get all users for stats
        ])->json();

        $allShotRes = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.shot_end_point') . '/api/admin/users', [
            "page" => 1,
            "per_page" => 10000 // Get all users for stats
        ])->json();

        // Merge data for recent users display (5 users)
        $shotData = collect($shotRes['data'] ?? [])->keyBy('user_id');
        $merged = collect($managerRes['data'] ?? [])->map(function ($user) use ($shotData) {
            $shot = $shotData->get($user['user_id'], []);
            return array_merge($user, $shot);
        });

        // Merge all users data for accurate stats calculation
        $allShotData = collect($allShotRes['data'] ?? [])->keyBy('user_id');
        $allMergedUsers = collect($allManagerRes['data'] ?? [])->map(function ($user) use ($allShotData) {
            $shot = $allShotData->get($user['user_id'], []);
            return array_merge($user, $shot);
        });

        // Calculate dynamic stats from all merged users
        $totalUsers = $allMergedUsers->count();

        $paidUsers = $allMergedUsers->filter(function ($user) {
            return ($user['manager_is_paid'] ?? false) || ($user['shot_is_paid'] ?? false);
        })->count();
        $freeUsers = $totalUsers - $paidUsers;

        $response = [
            'users' => $merged->values(),
            'total_users' => $totalUsers,
            'paid_users' => $paidUsers,
            'free_users' => $freeUsers,
            'trades' => $trades ?? ['waiting' => 0, 'running' => 0, 'pnl' => 0]
        ];

        return view('dashboard.index', compact('response'));
    }
}
