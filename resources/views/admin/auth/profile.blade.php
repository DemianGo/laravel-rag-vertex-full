@extends('admin.layouts.app')

@section('title', 'Meu Perfil')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Meu Perfil</h1>
                    <p class="text-gray-600">Gerencie suas informações pessoais</p>
                </div>
                <div class="text-sm text-gray-500">
                    Último login: {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca' }}
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Form -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informações do Perfil</h3>
                </div>
                <form method="POST" action="{{ route('admin.profile.update') }}" class="p-6 space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
                            <div class="mt-1">
                                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('name') border-red-500 @enderror">
                            </div>
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <div class="mt-1">
                                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('email') border-red-500 @enderror">
                            </div>
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Profile Info -->
        <div>
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informações da Conta</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tipo de Conta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($user->is_super_admin)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        Super Admin
                                    </span>
                                @elseif($user->is_admin)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Admin
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Usuário
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Membro desde</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('d/m/Y') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Último Login</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca' }}
                                @if($user->last_login_ip)
                                    <br><span class="text-gray-500 text-xs">IP: {{ $user->last_login_ip }}</span>
                                @endif
                            </dd>
                        </div>

                        @if($user->admin_permissions)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Permissões</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if(is_array($user->admin_permissions))
                                    @foreach($user->admin_permissions as $permission)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 mr-1 mb-1">
                                            {{ $permission }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-gray-500">Nenhuma permissão específica</span>
                                @endif
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-6 bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Ações Rápidas</h3>
                </div>
                <div class="p-6 space-y-3">
                    <a href="{{ route('admin.change-password') }}" 
                       class="block w-full text-center bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Alterar Senha
                    </a>
                    
                    <a href="{{ route('admin.dashboard') }}" 
                       class="block w-full text-center bg-indigo-100 text-indigo-700 px-4 py-2 rounded-md hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Informações de Segurança</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Mantenha suas credenciais seguras e não as compartilhe</li>
                        <li>Altere sua senha regularmente para maior segurança</li>
                        <li>Faça logout sempre que terminar de usar o sistema</li>
                        <li>Se você notar atividade suspeita, entre em contato com o suporte</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
