<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    /**
     * Fetch users from API and merge manager + shot data
     */
    private function fetchUsers($page = 1, $perPage = 20, $filter = null, $search = null, $filters = [])
    {
        // Prepare request payload
        $payload = [
            "page" => 1,
            "per_page" => 1000 // Fetch all users
        ];

        // Add search parameter if provided
        if ($search) {
            $payload['search'] = $search;
        }

        // Fetch ALL users from manager endpoint (for proper filtering)
        $managerRes = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.manager_end_point') . '/api/admin/users', $payload)->json();

        // Fetch ALL users from shot endpoint
        $shotRes = Http::withHeaders([
            'API-SECRET' => config('services.api.secret')
        ])->post(config('services.api.shot_end_point') . '/api/admin/users', $payload)->json();

        $shotData = collect($shotRes['data'] ?? [])->keyBy('user_id');

        // Merge data from both endpoints
        $merged = collect($managerRes['data'] ?? [])->map(function ($user) use ($shotData) {
            $shot = $shotData->get($user['user_id'], []);
            return array_merge($user, $shot);
        });

        // Apply legacy filter if specified (for backward compatibility)
        if ($filter === 'paid') {
            $merged = $merged->filter(function ($user) {
                return ($user['manager_is_paid'] ?? false) || ($user['shot_is_paid'] ?? false);
            })->values();
            \Log::info('After legacy "paid" filter: ' . $merged->count());
        } elseif ($filter === 'free') {
            $merged = $merged->filter(function ($user) {
                return !($user['manager_is_paid'] ?? false) && !($user['shot_is_paid'] ?? false);
            })->values();
            \Log::info('After legacy "free" filter: ' . $merged->count());
        }

        // Apply dynamic filters
        // Filter by Manager status (paid/trial)
        if (isset($filters['manager']) && $filters['manager'] !== 'all') {
            $beforeCount = $merged->count();
            $merged = $merged->filter(function ($user) use ($filters) {
                if ($filters['manager'] === 'paid') {
                    return isset($user['manager_is_paid']) && $user['manager_is_paid'] == true;
                } elseif ($filters['manager'] === 'trial') {
                    return !isset($user['manager_is_paid']) || $user['manager_is_paid'] == false;
                }
                return true;
            })->values();
            \Log::info('Manager filter "' . $filters['manager'] . '": ' . $beforeCount . ' -> ' . $merged->count());
        }

        // Filter by Shot status (paid/trial)
        if (isset($filters['shot']) && $filters['shot'] !== 'all') {
            $beforeCount = $merged->count();
            $merged = $merged->filter(function ($user) use ($filters) {
                if ($filters['shot'] === 'paid') {
                    return isset($user['shot_is_paid']) && $user['shot_is_paid'] == true;
                } elseif ($filters['shot'] === 'trial') {
                    return !isset($user['shot_is_paid']) || $user['shot_is_paid'] == false;
                }
                return true;
            })->values();
            \Log::info('Shot filter "' . $filters['shot'] . '": ' . $beforeCount . ' -> ' . $merged->count());
        }

        // Filter by Mode (active/passive)
        if (isset($filters['mod']) && $filters['mod'] !== 'all') {
            $beforeCount = $merged->count();
            $merged = $merged->filter(function ($user) use ($filters) {
                $mode = strtolower($user['money_management_status'] ?? 'passive');
                return $mode === $filters['mod'];
            })->values();
            \Log::info('Mode filter "' . $filters['mod'] . '": ' . $beforeCount . ' -> ' . $merged->count());
        }

        // Filter by Bot (bot1-bot8)
        if (isset($filters['bot']) && $filters['bot'] !== 'all') {
            $beforeCount = $merged->count();
            $merged = $merged->filter(function ($user) use ($filters) {
                $userBot = 'bot' . ($user['bot'] ?? '');
                return $userBot === $filters['bot'];
            })->values();
            \Log::info('Bot filter "' . $filters['bot'] . '": ' . $beforeCount . ' -> ' . $merged->count());
        }

        \Log::info('Final filtered count: ' . $merged->count());
        \Log::info('=== END DEBUG ===');

        // Calculate pagination
        $total = $merged->count();
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Get the slice of data for current page
        $paginatedData = $merged->slice($offset, $perPage)->values();

        return [
            'data' => $paginatedData,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, $lastPage),
        ];
    }

    //all
    public function all(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search', null);

        // Get dynamic filters
        $filters = [
            'manager' => $request->get('manager', 'all'),
            'shot' => $request->get('shot', 'all'),
            'mod' => $request->get('mod', 'all'),
            'bot' => $request->get('bot', 'all'),
        ];

        $response = $this->fetchUsers($page, $perPage, null, $search, $filters);

        // return $response;

        $headerTxt = "All Users";
        return view("users.index", compact('headerTxt', 'response'));
    }

    //paid
    public function paid(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search', null);

        // Get dynamic filters
        $filters = [
            'manager' => $request->get('manager', 'all'),
            'shot' => $request->get('shot', 'all'),
            'mod' => $request->get('mod', 'all'),
            'bot' => $request->get('bot', 'all'),
        ];

        $response = $this->fetchUsers($page, $perPage, 'paid', $search, $filters);

        $headerTxt = "Paid Users";
        return view("users.index", compact('headerTxt', 'response'));
    }

    //free
    public function free(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search', null);

        // Get dynamic filters
        $filters = [
            'manager' => $request->get('manager', 'all'),
            'shot' => $request->get('shot', 'all'),
            'mod' => $request->get('mod', 'all'),
            'bot' => $request->get('bot', 'all'),
        ];

        $response = $this->fetchUsers($page, $perPage, 'free', $search, $filters);

        $headerTxt = "Free Users";
        return view("users.index", compact('headerTxt', 'response'));
    }

    /**
     * Send message to a user via Telegram
     */
    public function sendMessage(Request $request)
    {
        try {
            $userId = $request->input('user_id');
            $message = $request->input('message');

            // Validate input
            if (!$userId || !$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID and message are required'
                ], 400);
            }

            // Get bot token from config
            $botToken = config('services.api.support_bot_token');

            if (!$botToken) {
                \Log::error('Bot token not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Bot token not configured'
                ], 500);
            }

            // Send message via Telegram API
            $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $response = Http::post($telegramApiUrl, [
                'chat_id' => $userId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            $result = $response->json();

            // Log the response for debugging
            \Log::info('Telegram API Response', [
                'user_id' => $userId,
                'success' => $result['ok'] ?? false,
                'response' => $result
            ]);

            if ($result['ok'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['description'] ?? 'Failed to send message'
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending message', [
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
