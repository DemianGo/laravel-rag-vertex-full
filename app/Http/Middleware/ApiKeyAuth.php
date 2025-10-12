<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract API key from request
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key in the Authorization header (Bearer token) or X-API-Key header.',
                'details' => [
                    'supported_headers' => [
                        'Authorization: Bearer <your-api-key>',
                        'X-API-Key: <your-api-key>'
                    ]
                ]
            ], 401);
        }

        // Find user by API key
        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            Log::warning('Invalid API key attempted', [
                'api_key_prefix' => substr($apiKey, 0, 12) . '...',
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid.',
            ], 401);
        }

        // Update last used timestamp
        if (app()->environment('testing')) {
            // Synchronous in tests
            $user->touchApiKey();
        } else {
            // Async in production to avoid blocking
            dispatch(function () use ($user) {
                $user->touchApiKey();
            })->afterResponse();
        }

        // Set authenticated user in request
        auth()->setUser($user);

        return $next($request);
    }

    /**
     * Extract API key from request headers.
     */
    private function extractApiKey(Request $request): ?string
    {
        // Try Authorization header (Bearer token)
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Try X-API-Key header
        return $request->header('X-API-Key');
    }
}
