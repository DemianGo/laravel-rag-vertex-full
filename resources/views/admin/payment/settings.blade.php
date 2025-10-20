@extends('admin.layouts.app')

@section('title', 'Configurações de Pagamento')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-credit-card"></i>
                        Configurações de Pagamento - Mercado Pago
                    </h3>
                </div>
                <div class="card-body">
                    <form id="payment-settings-form">
                        @csrf
                        
                        <!-- Configurações do Mercado Pago -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mercadopago_access_token">Access Token</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="mercadopago_access_token" 
                                           name="mercadopago_access_token"
                                           value="{{ isset($mercadoPagoConfigs['mercadopago_access_token']) ? $mercadoPagoConfigs['mercadopago_access_token']->config_value : '' }}"
                                           placeholder="APP_USR-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    <small class="form-text text-muted">Token de acesso do Mercado Pago</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mercadopago_public_key">Public Key</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="mercadopago_public_key" 
                                           name="mercadopago_public_key"
                                           value="{{ isset($mercadoPagoConfigs['mercadopago_public_key']) ? $mercadoPagoConfigs['mercadopago_public_key']->config_value : '' }}"
                                           placeholder="APP_USR-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    <small class="form-text text-muted">Chave pública do Mercado Pago</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               id="mercadopago_sandbox" 
                                               name="mercadopago_sandbox"
                                               {{ isset($mercadoPagoConfigs['mercadopago_sandbox']) && $mercadoPagoConfigs['mercadopago_sandbox']->config_value === '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="mercadopago_sandbox">
                                            Modo Sandbox (Teste)
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Use o ambiente de teste do Mercado Pago</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="payment_timeout">Timeout de Pagamento (minutos)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="payment_timeout" 
                                           name="payment_timeout"
                                           value="{{ isset($mercadoPagoConfigs['payment_timeout']) ? $mercadoPagoConfigs['payment_timeout']->config_value : '30' }}"
                                           min="5" 
                                           max="120">
                                    <small class="form-text text-muted">Tempo limite para processar pagamentos</small>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Configurações
                                </button>
                                <button type="button" class="btn btn-info" id="test-connection">
                                    <i class="fas fa-wifi"></i> Testar Conexão
                                </button>
                                <a href="{{ route('admin.payments') }}" class="btn btn-secondary">
                                    <i class="fas fa-list"></i> Ver Pagamentos
                                </a>
                                <a href="{{ route('admin.payment-stats') }}" class="btn btn-success">
                                    <i class="fas fa-chart-bar"></i> Estatísticas
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Planos Disponíveis -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tags"></i>
                        Planos Disponíveis para Pagamento
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($plans as $plan)
                        <div class="col-md-4">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">{{ $plan->plan_name }}</h5>
                                </div>
                                <div class="card-body">
                                    <h3 class="text-primary">R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}</h3>
                                    <p class="text-muted">por mês</p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> {{ number_format($plan->tokens_limit) }} tokens</li>
                                        <li><i class="fas fa-check text-success"></i> {{ $plan->documents_limit }} documentos</li>
                                        <li><i class="fas fa-check text-success"></i> 
                                            @if(is_array($plan->features))
                                                @foreach($plan->features as $feature)
                                                    <span class="badge badge-primary mr-1">{{ $feature }}</span>
                                                @endforeach
                                            @else
                                                {{ $plan->features }}
                                            @endif
                                        </li>
                                    </ul>
                                    <div class="mt-3">
                                        <span class="badge badge-{{ $plan->is_active ? 'success' : 'danger' }}">
                                            {{ $plan->is_active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Resultado do Teste -->
<div class="modal fade" id="test-result-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resultado do Teste</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="test-result-content">
                <!-- Conteúdo será inserido via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Salvar configurações
    $('#payment-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: '{{ route("admin.payment-settings.update") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                toastr.error(response?.message || 'Erro ao salvar configurações');
            }
        });
    });

    // Testar conexão
    $('#test-connection').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testando...');
        
        $.ajax({
            url: '{{ route("admin.payment-settings.test") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    $('#test-result-content').html(`
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle"></i> Conexão bem-sucedida!</h6>
                            <p>${response.message}</p>
                            <pre class="mt-3"><code>${JSON.stringify(response.data, null, 2)}</code></pre>
                        </div>
                    `);
                } else {
                    $('#test-result-content').html(`
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle"></i> Erro na conexão</h6>
                            <p>${response.message}</p>
                        </div>
                    `);
                }
                $('#test-result-modal').modal('show');
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                $('#test-result-content').html(`
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle"></i> Erro na conexão</h6>
                        <p>${response?.message || 'Erro desconhecido'}</p>
                    </div>
                `);
                $('#test-result-modal').modal('show');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
@endsection