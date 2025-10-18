<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Verifica se o usuário autenticado tem permissões de admin
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica se o usuário está autenticado
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Não autenticado. Faça login para acessar esta área.'
                ], 401);
            }
            
            return redirect()->route('admin.login')
                ->with('error', 'Você precisa estar logado para acessar esta área.');
        }

        $user = Auth::user();

        // Verifica se o usuário é admin
        if (!$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Acesso negado. Você não tem permissões de administrador.'
                ], 403);
            }

            abort(403, 'Acesso negado. Você não tem permissões de administrador.');
        }

        // Verifica se o usuário está ativo (se aplicável)
        if (method_exists($user, 'isActive') && !$user->isActive()) {
            Auth::logout();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sua conta está desativada.'
                ], 403);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Sua conta está desativada.');
        }

        return $next($request);
    }
}
