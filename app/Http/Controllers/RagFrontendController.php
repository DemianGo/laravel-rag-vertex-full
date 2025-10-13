<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RagFrontendController extends Controller
{
    /**
     * Show RAG frontend (requires authentication)
     */
    public function index()
    {
        // Redirect to login if not authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('info', 'Por favor, faça login para acessar o RAG Console.');
        }
        
        $user = Auth::user();
        
        // Pass user data to frontend
        return view('rag-frontend', [
            'user' => $user,
            'tenant_slug' => "user_{$user->id}",
            'api_token' => $user->createToken('rag-frontend')->plainTextToken
        ]);
    }
}

