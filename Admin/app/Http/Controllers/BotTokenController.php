<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotTokenController extends Controller
{
    /**
     * Fetch bot tokens from API with optional filtering
     */
    private function fetchBotTokens($page = 1, $perPage = 20, $filters = [])
    {
        try {
            // Prepare request payload
            $payload = [
                "page" => $page,
                "per_page" => $perPage
            ];

            // Add filters if provided
            if (isset($filters['type']) && $filters['type'] !== 'all') {
                $payload['type'] = $filters['type'];
            }

            if (isset($filters['is_active']) && $filters['is_active'] !== 'all') {
                $payload['is_active'] = $filters['is_active'];
            }

            // Fetch bot tokens from API
            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret')
            ])->get(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens', $payload);

            $result = $response->json();

            \Log::info($result);

            if (!$result || !isset($result['success']) || !$result['success']) {
                \Log::error('Failed to fetch bot tokens', ['response' => $result]);
                return [
                    'data' => [],
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ];
            }

            return [
                'data' => $result['data'] ?? [],
                'current_page' => $result['pagination']['current_page'] ?? 1,
                'per_page' => $result['pagination']['per_page'] ?? $perPage,
                'total' => $result['pagination']['total'] ?? 0,
                'last_page' => $result['pagination']['last_page'] ?? 1,
            ];
        } catch (\Exception $e) {
            \Log::error('Error fetching bot tokens', [
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ];
        }
    }

    /**
     * Display listing of bot tokens
     */
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        // Get filters
        $filters = [
            'type' => $request->get('type', 'all'),
            'is_active' => $request->get('is_active', 'all'),
        ];

        $response = $this->fetchBotTokens($page, $perPage, $filters);

        return view("bot-tokens.index", compact('response', 'filters'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view("bot-tokens.create");
    }

    /**
     * Store new bot token
     */
    public function store(Request $request)
    {
        try {
            $payload = [
                'name' => $request->input('name'),
                'token' => $request->input('token'),
                'type' => $request->input('type'),
                'max_user' => (int) $request->input('max_user'),
                'is_active' => $request->has('is_active') ? true : false,
            ];

            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret'),
                'Content-Type' => 'application/json'
            ])->post(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens', $payload);

            $result = $response->json();

            if ($result && isset($result['success']) && $result['success']) {
                return redirect()->route('bot-token.index')->with('success', 'Bot token created successfully');
            } else {
                $errorMessage = $result['message'] ?? 'Failed to create bot token';
                return back()->withInput()->with('error', $errorMessage);
            }
        } catch (\Exception $e) {
            \Log::error('Error creating bot token', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        try {
            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret')
            ])->get(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens/' . $id);

            $result = $response->json();

            if ($result && isset($result['success']) && $result['success']) {
                $botToken = $result['data'];
                return view("bot-tokens.edit", compact('botToken'));
            } else {
                return redirect()->route('bot-token.index')->with('error', 'Bot token not found');
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching bot token for edit', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('bot-token.index')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Update bot token
     */
    public function update(Request $request, $id)
    {
        try {
            $payload = [];

            if ($request->filled('name')) {
                $payload['name'] = $request->input('name');
            }

            if ($request->filled('token')) {
                $payload['token'] = $request->input('token');
            }

            if ($request->filled('type')) {
                $payload['type'] = $request->input('type');
            }

            if ($request->filled('max_user')) {
                $payload['max_user'] = (int) $request->input('max_user');
            }

            $payload['is_active'] = $request->has('is_active') ? true : false;

            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret'),
                'Content-Type' => 'application/json'
            ])->put(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens/' . $id, $payload);

            $result = $response->json();

            if ($result && isset($result['success']) && $result['success']) {
                return redirect()->route('bot-token.index')->with('success', 'Bot token updated successfully');
            } else {
                $errorMessage = $result['message'] ?? 'Failed to update bot token';
                return back()->withInput()->with('error', $errorMessage);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating bot token', [
                'id' => $id,
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete bot token
     */
    public function destroy($id)
    {
        try {
            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret')
            ])->delete(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens/' . $id);

            $result = $response->json();

            if ($result && isset($result['success']) && $result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bot token deleted successfully'
                ]);
            } else {
                $errorMessage = $result['message'] ?? 'Failed to delete bot token';
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            }
        } catch (\Exception $e) {
            \Log::error('Error deleting bot token', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle bot token status
     */
    public function toggleStatus($id)
    {
        try {
            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret')
            ])->post(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens/' . $id . '/toggle-status');

            $result = $response->json();

            if ($result && isset($result['success']) && $result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bot status toggled successfully',
                    'data' => $result['data']
                ]);
            } else {
                $errorMessage = $result['message'] ?? 'Failed to toggle bot status';
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            }
        } catch (\Exception $e) {
            \Log::error('Error toggling bot status', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bot token statistics
     */
    public function stats()
    {
        try {
            $response = Http::withHeaders([
                'API-SECRET' => config('services.api.secret')
            ])->get(config('services.api.manager_end_point') . '/api/admin/telegram-bot-tokens/stats');

            $result = $response->json();

            if ($result && isset($result['success']) && $result['success']) {
                return response()->json($result);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch statistics'
                ], 422);
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching bot token stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
