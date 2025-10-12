<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ApiKeyController extends Controller
{
    /**
     * Display the API key management page.
     */
    public function index()
    {
        $user = Auth::user();
        
        return view('api-keys.index', [
            'user' => $user,
            'hasApiKey' => $user->hasApiKey(),
            'maskedApiKey' => $user->masked_api_key,
            'apiKeyCreatedAt' => $user->api_key_created_at,
            'apiKeyLastUsedAt' => $user->api_key_last_used_at,
        ]);
    }

    /**
     * Generate a new API key for the authenticated user.
     */
    public function generate(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->hasApiKey()) {
            return response()->json([
                'error' => 'API key already exists',
                'message' => 'You already have an API key. Please regenerate if you need a new one.',
            ], 400);
        }

        $apiKey = $user->generateApiKey();

        return response()->json([
            'success' => true,
            'message' => 'API key generated successfully. Please save it securely as it won\'t be shown again.',
            'api_key' => $apiKey,
            'created_at' => $user->api_key_created_at->toIso8601String(),
        ]);
    }

    /**
     * Regenerate the API key for the authenticated user.
     */
    public function regenerate(Request $request): JsonResponse
    {
        $user = Auth::user();

        $apiKey = $user->regenerateApiKey();

        return response()->json([
            'success' => true,
            'message' => 'API key regenerated successfully. Please save it securely as it won\'t be shown again. Your old API key has been invalidated.',
            'api_key' => $apiKey,
            'created_at' => $user->api_key_created_at->toIso8601String(),
        ]);
    }

    /**
     * Get API key information (masked).
     */
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasApiKey()) {
            return response()->json([
                'has_api_key' => false,
                'message' => 'No API key found. Please generate one first.',
            ]);
        }

        return response()->json([
            'has_api_key' => true,
            'masked_api_key' => $user->masked_api_key,
            'created_at' => $user->api_key_created_at?->toIso8601String(),
            'last_used_at' => $user->api_key_last_used_at?->toIso8601String(),
        ]);
    }

    /**
     * Revoke (delete) the API key.
     */
    public function revoke(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasApiKey()) {
            return response()->json([
                'error' => 'No API key found',
                'message' => 'You don\'t have an API key to revoke.',
            ], 400);
        }

        $user->update([
            'api_key' => null,
            'api_key_created_at' => null,
            'api_key_last_used_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully.',
        ]);
    }

    /**
     * Test endpoint to validate API key authentication.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('userPlan');

        $planData = null;
        if ($user->userPlan) {
            $planData = [
                'plan' => $user->userPlan->plan,
                'tokens_used' => $user->userPlan->tokens_used,
                'tokens_limit' => $user->userPlan->tokens_limit,
                'documents_used' => $user->userPlan->documents_used,
                'documents_limit' => $user->userPlan->documents_limit,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'API key is valid!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'plan' => $planData,
        ]);
    }
}
