@extends('admin.layouts.app')

@section('title', 'Alterar Senha')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Alterar Senha</h1>
                    <p class="text-gray-600">Atualize sua senha para manter sua conta segura</p>
                </div>
                <div>
                    <a href="{{ route('admin.profile') }}" 
                       class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        ← Voltar ao Perfil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-md mx-auto">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <form method="POST" action="{{ route('admin.change-password.update') }}" class="p-6 space-y-6">
                @csrf
                @method('PATCH')

                <!-- Current Password -->
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">
                        Senha Atual
                    </label>
                    <div class="mt-1">
                        <input type="password" name="current_password" id="current_password" required
                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('current_password') border-red-500 @enderror">
                    </div>
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- New Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Nova Senha
                    </label>
                    <div class="mt-1">
                        <input type="password" name="password" id="password" required minlength="8"
                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('password') border-red-500 @enderror">
                    </div>
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Mínimo de 8 caracteres</p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                        Confirmar Nova Senha
                    </label>
                    <div class="mt-1">
                        <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8"
                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Requisitos da Senha:</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• Mínimo de 8 caracteres</li>
                        <li>• Use uma combinação de letras, números e símbolos</li>
                        <li>• Evite senhas óbvias ou comuns</li>
                        <li>• Não use informações pessoais</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.profile') }}" 
                       class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Alterar Senha
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Tips -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Dicas de Segurança</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Use uma senha única que você não usa em outros sites</li>
                            <li>Considere usar um gerenciador de senhas</li>
                            <li>Nunca compartilhe sua senha com outras pessoas</li>
                            <li>Altere sua senha regularmente</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validação de senha em tempo real
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('password_confirmation');
    
    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('As senhas não coincidem');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
});
</script>
@endsection
