<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Mostra o formulário de login do admin
     */
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    /**
     * Processa o login do admin
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        // Tenta autenticar como usuário normal primeiro
        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            
            // Verifica se é admin
            if ($user->isAdmin()) {
                $request->session()->regenerate();
                
                // Atualiza último login
                $user->updateLastLogin($request->ip());
                
                return redirect()->intended(route('admin.dashboard'))
                    ->with('success', 'Login realizado com sucesso!');
            } else {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'Acesso negado. Apenas administradores podem acessar esta área.',
                ]);
            }
        }

        throw ValidationException::withMessages([
            'email' => 'As credenciais fornecidas não correspondem aos nossos registros.',
        ]);
    }

    /**
     * Logout do admin
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'Logout realizado com sucesso!');
    }

    /**
     * Mostra o formulário de alteração de senha
     */
    public function showChangePasswordForm()
    {
        return view('admin.auth.change-password');
    }

    /**
     * Processa a alteração de senha
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verifica a senha atual
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'A senha atual está incorreta.',
            ]);
        }

        // Atualiza a senha
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Senha alterada com sucesso!');
    }

    /**
     * Mostra o perfil do admin
     */
    public function profile()
    {
        $user = Auth::user();
        return view('admin.auth.profile', compact('user'));
    }

    /**
     * Atualiza o perfil do admin
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return redirect()->route('admin.profile')
            ->with('success', 'Perfil atualizado com sucesso!');
    }
}
