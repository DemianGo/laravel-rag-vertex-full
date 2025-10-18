@extends('admin.layouts.app')

@section('title', 'Estatísticas de Pagamentos')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar"></i>
                        Estatísticas de Pagamentos
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.payments') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-list"></i> Ver Pagamentos
                        </a>
                        <a href="{{ route('admin.payment-settings') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-cog"></i> Configurações
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Cards de Estatísticas -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>{{ number_format($stats['total_payments']) }}</h3>
                                    <p>Total de Pagamentos</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>{{ number_format($stats['approved_payments']) }}</h3>
                                    <p>Pagamentos Aprovados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>{{ number_format($stats['pending_payments']) }}</h3>
                                    <p>Pagamentos Pendentes</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3>{{ number_format($stats['rejected_payments']) }}</h3>
                                    <p>Pagamentos Rejeitados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cards de Receita -->
                    <div class="row mb-4">
                        <div class="col-lg-4 col-6">
                            <div class="small-box bg-primary">
                                <div class="inner">
                                    <h3>R$ {{ number_format($stats['total_revenue'], 2, ',', '.') }}</h3>
                                    <p>Receita Total</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>R$ {{ number_format($stats['monthly_revenue'], 2, ',', '.') }}</h3>
                                    <p>Receita Mensal</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-calendar-month"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>R$ {{ number_format($stats['average_payment'], 2, ',', '.') }}</h3>
                                    <p>Ticket Médio</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Taxa de Conversão -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0">Taxa de Conversão</h5>
                                </div>
                                <div class="card-body text-center">
                                    <h2 class="text-success">{{ $stats['conversion_rate'] }}%</h2>
                                    <p class="mb-0">Pagamentos Aprovados / Total de Pagamentos</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Receita Anual</h5>
                                </div>
                                <div class="card-body text-center">
                                    <h2 class="text-primary">R$ {{ number_format($stats['yearly_revenue'], 2, ',', '.') }}</h2>
                                    <p class="mb-0">Receita total do ano atual</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="row">
                        <!-- Gráfico de Receita por Mês -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Receita por Mês (Últimos 12 meses)</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Status -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Pagamentos por Status</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="statusChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Resumo -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Resumo Detalhado</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Métrica</th>
                                                    <th>Valor</th>
                                                    <th>Percentual</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Total de Pagamentos</td>
                                                    <td>{{ number_format($stats['total_payments']) }}</td>
                                                    <td>100%</td>
                                                </tr>
                                                <tr class="table-success">
                                                    <td>Pagamentos Aprovados</td>
                                                    <td>{{ number_format($stats['approved_payments']) }}</td>
                                                    <td>{{ $stats['total_payments'] > 0 ? round(($stats['approved_payments'] / $stats['total_payments']) * 100, 2) : 0 }}%</td>
                                                </tr>
                                                <tr class="table-warning">
                                                    <td>Pagamentos Pendentes</td>
                                                    <td>{{ number_format($stats['pending_payments']) }}</td>
                                                    <td>{{ $stats['total_payments'] > 0 ? round(($stats['pending_payments'] / $stats['total_payments']) * 100, 2) : 0 }}%</td>
                                                </tr>
                                                <tr class="table-danger">
                                                    <td>Pagamentos Rejeitados</td>
                                                    <td>{{ number_format($stats['rejected_payments']) }}</td>
                                                    <td>{{ $stats['total_payments'] > 0 ? round(($stats['rejected_payments'] / $stats['total_payments']) * 100, 2) : 0 }}%</td>
                                                </tr>
                                            </tbody>
                                        </table>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Gráfico de Receita por Mês
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = @json($revenueChart);
    
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueData.map(item => {
                const [year, month] = item.month.split('-');
                const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 
                                  'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                return `${monthNames[parseInt(month) - 1]}/${year}`;
            }),
            datasets: [{
                label: 'Receita (R$)',
                data: revenueData.map(item => item.total),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(2);
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Receita: R$ ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            }
        }
    });

    // Gráfico de Status
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = @json($statusChart);
    
    const statusLabels = statusData.map(item => {
        const labels = {
            'approved': 'Aprovados',
            'pending': 'Pendentes',
            'rejected': 'Rejeitados',
            'cancelled': 'Cancelados'
        };
        return labels[item.status] || item.status;
    });
    
    const statusColors = statusData.map(item => {
        const colors = {
            'approved': '#28a745',
            'pending': '#ffc107',
            'rejected': '#dc3545',
            'cancelled': '#6c757d'
        };
        return colors[item.status] || '#6c757d';
    });
    
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: statusColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = statusData.reduce((sum, item) => sum + item.count, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endsection