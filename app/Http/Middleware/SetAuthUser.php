<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetAuthUser
{
    /**
     * Handle an incoming request.
     * Define o usuário autenticado no contexto auth() padrão
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Tenta autenticar via sessão web primeiro
        if (Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();
            Auth::setDefaultDriver('web');
            return $next($request);
        }
        
        // Tenta autenticar via Sanctum (API token)
        if (Auth::guard('sanctum')->check()) {
            $sanctumUser = Auth::guard('sanctum')->user();
            Auth::setDefaultDriver('sanctum');
            return $next($request);
        }
        
        // Se nenhum método funcionou, retorna 401
        Log::warning('SetAuthUser: Nenhuma autenticação válida encontrada');
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    }
}
