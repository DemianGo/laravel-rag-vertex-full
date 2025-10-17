<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithSanctumOrSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = null;
        try {
            $sessionId = $request->session()->getId();
        } catch (\Exception $e) {
            // Sessão não disponível em rotas API
        }
        
        Log::info('AuthenticateWithSanctumOrSession: Verificando autenticação', [
            'web_check' => Auth::guard('web')->check(),
            'sanctum_check' => Auth::guard('sanctum')->check(),
            'user_web' => Auth::guard('web')->user() ? Auth::guard('web')->user()->id : null,
            'user_sanctum' => Auth::guard('sanctum')->user() ? Auth::guard('sanctum')->user()->id : null,
            'session_id' => $sessionId,
            'url' => $request->url()
        ]);
        
        // Tenta autenticar via sessão web primeiro
        if (Auth::guard('web')->check()) {
            Log::info('AuthenticateWithSanctumOrSession: Autenticado via sessão web');
            // Define o usuário no contexto auth() padrão usando login temporário
            $webUser = Auth::guard('web')->user();
            Auth::login($webUser, false); // false = não lembrar login
            Log::info('AuthenticateWithSanctumOrSession: Usuário definido no contexto padrão', [
                'user_id' => $webUser->id,
                'auth_check' => Auth::check()
            ]);
            return $next($request);
        }
        
        // Tenta autenticar via Sanctum (API token)
        if (Auth::guard('sanctum')->check()) {
            Log::info('AuthenticateWithSanctumOrSession: Autenticado via Sanctum');
            // Define o usuário no contexto auth() padrão usando login temporário
            $sanctumUser = Auth::guard('sanctum')->user();
            Auth::login($sanctumUser, false); // false = não lembrar login
            Log::info('AuthenticateWithSanctumOrSession: Usuário definido no contexto padrão', [
                'user_id' => $sanctumUser->id,
                'auth_check' => Auth::check()
            ]);
            return $next($request);
        }
        
        // Se nenhum método funcionou, retorna 401
        Log::warning('AuthenticateWithSanctumOrSession: Nenhuma autenticação válida encontrada');
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    }
}
