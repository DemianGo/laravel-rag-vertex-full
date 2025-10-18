@extends('admin.layouts.app')

@section('title', 'Gerenciar Pagamentos')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-credit-card"></i>
                        Gerenciar Pagamentos
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.payment-settings') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-cog"></i> Configurações
                        </a>
                        <a href="{{ route('admin.payment-stats') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-chart-bar"></i> Estatísticas
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="paymentsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Plano</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Gateway</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payments as $payment)
                                <tr>
                                    <td>{{ $payment->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $payment->user->name }}</strong><br>
                                            <small class="text-muted">{{ $payment->user->email }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($payment->subscription && $payment->subscription->planConfig)
                                            <span class="badge badge-info">{{ $payment->subscription->planConfig->plan_name }}</span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>R$ {{ number_format($payment->amount, 2, ',', '.') }}</strong>
                                    </td>
                                    <td>
                                        @switch($payment->status)
                                            @case('approved')
                                                <span class="badge badge-success">Aprovado</span>
                                                @break
                                            @case('pending')
                                                <span class="badge badge-warning">Pendente</span>
                                                @break
                                            @case('rejected')
                                                <span class="badge badge-danger">Rejeitado</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge badge-secondary">Cancelado</span>
                                                @break
                                            @default
                                                <span class="badge badge-light">{{ $payment->status }}</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">{{ ucfirst($payment->gateway) }}</span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $payment->created_at->format('d/m/Y') }}</strong><br>
                                            <small class="text-muted">{{ $payment->created_at->format('H:i:s') }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.payments.details', $payment->id) }}" 
                                               class="btn btn-info btn-sm" 
                                               title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($payment->status === 'pending')
                                            <button class="btn btn-success btn-sm" 
                                                    onclick="updatePaymentStatus({{ $payment->id }}, 'approved')"
                                                    title="Aprovar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="updatePaymentStatus({{ $payment->id }}, 'rejected')"
                                                    title="Rejeitar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($payments instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="card-footer">
                    {{ $payments->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Inicializar DataTable
    $('#paymentsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        }
    });
});

function updatePaymentStatus(paymentId, status) {
    const statusText = {
        'approved': 'aprovar',
        'rejected': 'rejeitar',
        'cancelled': 'cancelar'
    };
    
    if (confirm(`Tem certeza que deseja ${statusText[status]} este pagamento?`)) {
        $.ajax({
            url: `/admin/payments/${paymentId}/status`,
            method: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                status: status
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
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