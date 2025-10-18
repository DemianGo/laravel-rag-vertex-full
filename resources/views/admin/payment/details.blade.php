@extends('admin.layouts.app')

@section('title', 'Detalhes do Pagamento')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-credit-card"></i>
                        Detalhes do Pagamento #{{ $payment->id }}
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.payments') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Informações do Pagamento -->
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Informações do Pagamento</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>{{ $payment->id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Valor:</strong></td>
                                            <td><span class="badge badge-success badge-lg">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Moeda:</strong></td>
                                            <td>{{ $payment->currency }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                @switch($payment->status)
                                                    @case('approved')
                                                        <span class="badge badge-success badge-lg">Aprovado</span>
                                                        @break
                                                    @case('pending')
                                                        <span class="badge badge-warning badge-lg">Pendente</span>
                                                        @break
                                                    @case('rejected')
                                                        <span class="badge badge-danger badge-lg">Rejeitado</span>
                                                        @break
                                                    @case('cancelled')
                                                        <span class="badge badge-secondary badge-lg">Cancelado</span>
                                                        @break
                                                    @default
                                                        <span class="badge badge-light badge-lg">{{ $payment->status }}</span>
                                                @endswitch
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Gateway:</strong></td>
                                            <td><span class="badge badge-primary">{{ ucfirst($payment->gateway) }}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Método:</strong></td>
                                            <td>{{ ucfirst($payment->payment_method ?? 'N/A') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Criado em:</strong></td>
                                            <td>{{ $payment->created_at->format('d/m/Y H:i:s') }}</td>
                                        </tr>
                                        @if($payment->paid_at)
                                        <tr>
                                            <td><strong>Pago em:</strong></td>
                                            <td>{{ $payment->paid_at->format('d/m/Y H:i:s') }}</td>
                                        </tr>
                                        @endif
                                        @if(isset($payment->expires_at) && $payment->expires_at)
                                        <tr>
                                            <td><strong>Expira em:</strong></td>
                                            <td>{{ $payment->expires_at->format('d/m/Y H:i:s') }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Informações do Usuário -->
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">Informações do Usuário</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>{{ $payment->user->id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nome:</strong></td>
                                            <td>{{ $payment->user->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td>{{ $payment->user->email }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Plano Atual:</strong></td>
                                            <td><span class="badge badge-info">{{ $payment->user->plan }}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Admin:</strong></td>
                                            <td>
                                                @if(isset($payment->user->is_admin) && $payment->user->is_admin)
                                                    <span class="badge badge-warning">Sim</span>
                                                @else
                                                    <span class="badge badge-secondary">Não</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Registrado em:</strong></td>
                                            <td>{{ isset($payment->user->created_at) ? $payment->user->created_at->format('d/m/Y H:i:s') : 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informações da Assinatura -->
                    @if($payment->subscription)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0">Informações da Assinatura</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>ID da Assinatura:</strong></td>
                                                    <td>{{ $payment->subscription->id }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Status:</strong></td>
                                                    <td>
                                                        @switch($payment->subscription->status)
                                                            @case('active')
                                                                <span class="badge badge-success">Ativa</span>
                                                                @break
                                                            @case('pending')
                                                                <span class="badge badge-warning">Pendente</span>
                                                                @break
                                                            @case('cancelled')
                                                                <span class="badge badge-danger">Cancelada</span>
                                                                @break
                                                            @default
                                                                <span class="badge badge-light">{{ $payment->subscription->status }}</span>
                                                        @endswitch
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Valor:</strong></td>
                                                    <td>R$ {{ number_format($payment->subscription->amount, 2, ',', '.') }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Início:</strong></td>
                                                    <td>{{ $payment->subscription->starts_at ? $payment->subscription->starts_at->format('d/m/Y H:i:s') : 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Fim:</strong></td>
                                                    <td>{{ $payment->subscription->ends_at ? $payment->subscription->ends_at->format('d/m/Y H:i:s') : 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Renovação Automática:</strong></td>
                                                    <td>
                                                        @if($payment->subscription->auto_renew)
                                                            <span class="badge badge-success">Sim</span>
                                                        @else
                                                            <span class="badge badge-secondary">Não</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    @if($payment->subscription->planConfig)
                                    <div class="mt-3">
                                        <h6><strong>Detalhes do Plano:</strong></h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Nome:</strong> {{ $payment->subscription->planConfig->plan_name }}
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Tokens:</strong> {{ number_format($payment->subscription->planConfig->tokens_limit) }}
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Documentos:</strong> {{ $payment->subscription->planConfig->documents_limit }}
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <strong>Recursos:</strong> {{ $payment->subscription->planConfig->features }}
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Dados do Gateway -->
                    @if($payment->gateway_data)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="card-title mb-0">Dados do Gateway</h5>
                                </div>
                                <div class="card-body">
                                    <pre class="bg-light p-3 rounded"><code>{{ json_encode(json_decode($payment->gateway_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Metadados -->
                    @if($payment->metadata)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-secondary">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="card-title mb-0">Metadados</h5>
                                </div>
                                <div class="card-body">
                                    <pre class="bg-light p-3 rounded"><code>{{ json_encode(json_decode($payment->metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Ações -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Ações</h5>
                                </div>
                                <div class="card-body">
                                    <div class="btn-group" role="group">
                                        @if($payment->status === 'pending')
                                        <button class="btn btn-success" onclick="updatePaymentStatus('approved')">
                                            <i class="fas fa-check"></i> Aprovar Pagamento
                                        </button>
                                        <button class="btn btn-danger" onclick="updatePaymentStatus('rejected')">
                                            <i class="fas fa-times"></i> Rejeitar Pagamento
                                        </button>
                                        <button class="btn btn-warning" onclick="updatePaymentStatus('cancelled')">
                                            <i class="fas fa-ban"></i> Cancelar Pagamento
                                        </button>
                                        @endif
                                        
                                        <a href="{{ route('admin.users.show', $payment->user->id) }}" class="btn btn-info">
                                            <i class="fas fa-user"></i> Ver Usuário
                                        </a>
                                        
                                        <a href="{{ route('admin.payments') }}" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Voltar à Lista
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function updatePaymentStatus(status) {
    const statusText = {
        'approved': 'aprovar',
        'rejected': 'rejeitar',
        'cancelled': 'cancelar'
    };
    
    if (confirm(`Tem certeza que deseja ${statusText[status]} este pagamento?`)) {
        $.ajax({
            url: '{{ route("admin.payments.status", $payment->id) }}',
            method: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                status: status
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                toastr.error(response?.message || 'Erro ao atualizar status');
            }
        });
    }
}
</script>
@endsection