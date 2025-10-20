@extends('layouts.app')

@section('title', 'Preços - LiberAI')

@section('content')
<style>
/* Reset e estilos base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
}

/* Container principal */
.pricing-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 80px 20px;
}

/* Header */
.pricing-header {
    text-align: center;
    margin-bottom: 80px;
}

.pricing-title {
    font-size: 3.5rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 24px;
    letter-spacing: -0.02em;
}

.pricing-subtitle {
    font-size: 1.25rem;
    color: #666;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Grid de planos */
.pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 32px;
    margin-bottom: 80px;
}

/* Card de plano */
.pricing-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 16px;
    padding: 40px 32px;
    position: relative;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.pricing-card:hover {
    border-color: #007bff;
    box-shadow: 0 20px 40px rgba(0, 123, 255, 0.1);
    transform: translateY(-4px);
}

.pricing-card.popular {
    border-color: #007bff;
    box-shadow: 0 20px 40px rgba(0, 123, 255, 0.15);
}

/* Badge popular */
.popular-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    padding: 8px 24px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

/* Nome do plano */
.plan-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.plan-description {
    color: #666;
    margin-bottom: 32px;
    font-size: 1rem;
}

/* Preço */
.plan-price {
    margin-bottom: 32px;
}

.price-amount {
    font-size: 3rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1;
}

.price-currency {
    font-size: 1.5rem;
    font-weight: 600;
    color: #666;
    vertical-align: top;
}

.price-period {
    color: #666;
    font-size: 1rem;
    margin-top: 8px;
}

/* Lista de recursos */
.plan-features {
    list-style: none;
    margin-bottom: 40px;
    flex-grow: 1;
}

.plan-features li {
    display: flex;
    align-items: flex-start;
    margin-bottom: 16px;
    font-size: 1rem;
    color: #333;
}

.check-icon {
    width: 20px;
    height: 20px;
    background: #28a745;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    margin-top: 2px;
}

.check-icon::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* Botão CTA */
.plan-button {
    width: 100%;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 16px 24px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.plan-button:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
}

.plan-button.secondary {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #e5e5e5;
}

.plan-button.secondary:hover {
    background: #e9ecef;
    border-color: #007bff;
    color: #007bff;
}

/* Seção de comparação */
.comparison-section {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 60px 40px;
    margin-bottom: 80px;
}

.comparison-title {
    font-size: 2.5rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 16px;
    color: #1a1a1a;
}

.comparison-subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 48px;
    font-size: 1.125rem;
}

/* Tabela de comparação */
.comparison-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.comparison-table table {
    width: 100%;
    border-collapse: collapse;
}

.comparison-table th,
.comparison-table td {
    padding: 20px 24px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}

.comparison-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
    font-size: 1.125rem;
}

.comparison-table th:first-child {
    font-weight: 700;
}

.comparison-table td {
    color: #666;
}

.comparison-table tr:last-child td {
    border-bottom: none;
}

/* FAQ Section */
.faq-section {
    background: white;
    border-radius: 16px;
    padding: 60px 40px;
}

.faq-title {
    font-size: 2.5rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 16px;
    color: #1a1a1a;
}

.faq-subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 48px;
    font-size: 1.125rem;
}

.faq-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 32px;
}

.faq-item {
    padding: 24px;
    background: #f8f9fa;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.faq-item:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.faq-question {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 12px;
}

.faq-answer {
    color: #666;
    line-height: 1.6;
}

/* Modal de checkout */
.checkout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
}

.close-button {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 4px;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.payment-method {
    border: 2px solid #e5e5e5;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method:hover {
    border-color: #007bff;
}

.payment-method.selected {
    border-color: #007bff;
    background: #f0f8ff;
}

.payment-icon {
    font-size: 2rem;
    margin-bottom: 8px;
}

.payment-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #333;
}

.modal-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
}

.btn-secondary {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 12px 24px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 24px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #e9ecef;
}

.btn-primary:hover {
    background: #0056b3;
}

/* Footer Moderno 2025 */
.modern-footer {
    background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
    color: #ffffff;
    margin-top: 120px;
    position: relative;
    overflow: hidden;
}

.modern-footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, #007bff, transparent);
}

.footer-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 80px 24px 0;
}

.footer-main {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 80px;
    margin-bottom: 60px;
}

.footer-brand {
    max-width: 400px;
}

.brand-name {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #007bff, #00d4ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 20px;
}

.brand-description {
    color: #b8bcc8;
    line-height: 1.6;
    margin-bottom: 32px;
    font-size: 1.125rem;
}

.footer-cta {
    margin-bottom: 40px;
}

.cta-button {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 28px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 123, 255, 0.3);
}

.cta-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 123, 255, 0.4);
}

.footer-links-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 40px;
}

.footer-column {
    display: flex;
    flex-direction: column;
}

.column-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 24px;
    position: relative;
}

.column-title::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 24px;
    height: 2px;
    background: linear-gradient(90deg, #007bff, #00d4ff);
    border-radius: 1px;
}

.footer-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.footer-item {
    color: #b8bcc8;
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    position: relative;
    padding-left: 0;
}

.footer-item:hover {
    color: #ffffff;
    padding-left: 8px;
}

.footer-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 1px;
    background: linear-gradient(90deg, #007bff, #00d4ff);
    transition: width 0.3s ease;
}

.footer-item:hover::before {
    width: 6px;
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 40px 0;
    position: relative;
}

.footer-bottom::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 1px;
    background: linear-gradient(90deg, transparent, #007bff, transparent);
}

.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.copyright {
    color: #8b8f9a;
    font-size: 0.875rem;
    margin: 0;
}

.footer-social {
    display: flex;
    gap: 16px;
}

.social-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #b8bcc8;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.social-icon:hover {
    background: rgba(0, 123, 255, 0.1);
    border-color: rgba(0, 123, 255, 0.3);
    color: #007bff;
    transform: translateY(-2px);
}

/* Responsividade */
@media (max-width: 768px) {
    .pricing-container {
        padding: 40px 16px;
    }
    
    .pricing-title {
        font-size: 2.5rem;
    }
    
    .pricing-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .comparison-section,
    .faq-section {
        padding: 40px 24px;
    }
    
    .modal-content {
        padding: 24px;
        margin: 20px;
    }
    
    .footer-wrapper {
        padding: 60px 20px 0;
    }
    
    .footer-main {
        grid-template-columns: 1fr;
        gap: 60px;
    }
    
    .footer-links-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 32px;
    }
    
    .footer-bottom-content {
        flex-direction: column;
        gap: 24px;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .footer-links-grid {
        grid-template-columns: 1fr;
        gap: 32px;
    }
    
    .footer-social {
        justify-content: center;
    }
}
</style>

<div class="pricing-container">
    <!-- Header -->
    <div class="pricing-header">
        <h1 class="pricing-title">Escolha o Plano Ideal</h1>
        <p class="pricing-subtitle">
            Acesse recursos avançados de IA para processar seus documentos e obter insights inteligentes. 
            Todos os planos incluem suporte completo e atualizações automáticas.
        </p>
    </div>

    <!-- Grid de Planos -->
    <div class="pricing-grid">
        @foreach($plans as $index => $plan)
        <div class="pricing-card {{ $index === 1 ? 'popular' : '' }}">
            @if($index === 1)
            <div class="popular-badge">Mais Popular</div>
            @endif
            
            <h3 class="plan-name">{{ $plan->plan_name }}</h3>
            <p class="plan-description">{{ $plan->description }}</p>
            
            <div class="plan-price">
                <div class="price-amount">
                    <span class="price-currency">R$</span>{{ number_format($plan->price_monthly, 0, ',', '.') }}
                </div>
                <div class="price-period">por mês</div>
            </div>
            
            <ul class="plan-features">
                <li>
                    <span class="check-icon"></span>
                    {{ number_format($plan->tokens_limit) }} tokens de IA
                </li>
                <li>
                    <span class="check-icon"></span>
                    {{ $plan->documents_limit }} documentos
                </li>
                <li>
                    <span class="check-icon"></span>
                    @if(is_array($plan->features))
                        Recursos especiais: {{ implode(', ', $plan->features) }}
                    @else
                        {{ $plan->features }}
                    @endif
                </li>
                <li>
                    <span class="check-icon"></span>
                    Suporte 24/7
                </li>
                <li>
                    <span class="check-icon"></span>
                    Atualizações automáticas
                </li>
            </ul>
            
            <button onclick="openCheckoutModal('{{ $plan->plan_name }}')" 
                    class="plan-button {{ $index === 1 ? '' : 'secondary' }}">
                Escolher Plano
            </button>
        </div>
        @endforeach
    </div>

    <!-- Seção de Comparação -->
    <div class="comparison-section">
        <h2 class="comparison-title">Comparação de Planos</h2>
        <p class="comparison-subtitle">Compare todos os recursos e escolha o que melhor atende suas necessidades</p>
        
        <div class="comparison-table">
            <table>
                <thead>
                    <tr>
                        <th>Recursos</th>
                        @foreach($plans as $plan)
                        <th>{{ $plan->plan_name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Tokens de IA</td>
                        @foreach($plans as $plan)
                        <td>{{ number_format($plan->tokens_limit) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Documentos</td>
                        @foreach($plans as $plan)
                        <td>{{ $plan->documents_limit }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Suporte</td>
                        @foreach($plans as $plan)
                        <td>24/7</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Preço Mensal</td>
                        @foreach($plans as $plan)
                        <td>R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FAQ -->
    <div class="faq-section">
        <h2 class="faq-title">Perguntas Frequentes</h2>
        <p class="faq-subtitle">Tire suas dúvidas sobre nossos planos e serviços</p>
        
        <div class="faq-grid">
            <div class="faq-item">
                <h3 class="faq-question">Posso cancelar a qualquer momento?</h3>
                <p class="faq-answer">Sim, você pode cancelar sua assinatura a qualquer momento. Não há taxas de cancelamento e você mantém acesso aos recursos até o final do período pago.</p>
            </div>
            <div class="faq-item">
                <h3 class="faq-question">Os dados são seguros?</h3>
                <p class="faq-answer">Sim, todos os dados são criptografados e armazenados com segurança. Utilizamos criptografia de ponta e nunca compartilhamos suas informações com terceiros.</p>
            </div>
            <div class="faq-item">
                <h3 class="faq-question">Posso alterar meu plano?</h3>
                <p class="faq-answer">Sim, você pode fazer upgrade ou downgrade do seu plano a qualquer momento. As alterações são aplicadas imediatamente e os valores são ajustados proporcionalmente.</p>
            </div>
            <div class="faq-item">
                <h3 class="faq-question">Há período de teste?</h3>
                <p class="faq-answer">O plano Free oferece recursos limitados para você testar a plataforma sem compromisso. Experimente todas as funcionalidades antes de escolher um plano pago.</p>
            </div>
        </div>
    </div>

    <!-- Footer Moderno 2025 -->
    <footer class="modern-footer">
        <div class="footer-wrapper">
            <div class="footer-main">
                <div class="footer-brand">
                    <h3 class="brand-name">LiberAI</h3>
                    <p class="brand-description">
                        Transforme seus documentos com IA avançada. Processamento inteligente, insights precisos, resultados instantâneos.
                    </p>
                    <div class="footer-cta">
                        <button class="cta-button">Começar Agora</button>
                    </div>
                </div>
                
                <div class="footer-links-grid">
                    <div class="footer-column">
                        <h4 class="column-title">Produto</h4>
                        <ul class="footer-list">
                            <li><a href="#" class="footer-item">Recursos</a></li>
                            <li><a href="#" class="footer-item">Preços</a></li>
                            <li><a href="#" class="footer-item">API</a></li>
                            <li><a href="#" class="footer-item">Integrações</a></li>
                            <li><a href="#" class="footer-item">Changelog</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h4 class="column-title">Suporte</h4>
                        <ul class="footer-list">
                            <li><a href="#" class="footer-item">Central de Ajuda</a></li>
                            <li><a href="#" class="footer-item">Documentação</a></li>
                            <li><a href="#" class="footer-item">Comunidade</a></li>
                            <li><a href="#" class="footer-item">Status</a></li>
                            <li><a href="#" class="footer-item">Contato</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h4 class="column-title">Empresa</h4>
                        <ul class="footer-list">
                            <li><a href="#" class="footer-item">Sobre</a></li>
                            <li><a href="#" class="footer-item">Blog</a></li>
                            <li><a href="#" class="footer-item">Carreiras</a></li>
                            <li><a href="#" class="footer-item">Imprensa</a></li>
                            <li><a href="#" class="footer-item">Parceiros</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h4 class="column-title">Legal</h4>
                        <ul class="footer-list">
                            <li><a href="#" class="footer-item">Termos de Uso</a></li>
                            <li><a href="#" class="footer-item">Privacidade</a></li>
                            <li><a href="#" class="footer-item">Cookies</a></li>
                            <li><a href="#" class="footer-item">Segurança</a></li>
                            <li><a href="#" class="footer-item">GDPR</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p class="copyright">© 2024 LiberAI. Todos os direitos reservados.</p>
                    <div class="footer-social">
                        <a href="#" class="social-icon" aria-label="Twitter">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-icon" aria-label="LinkedIn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-icon" aria-label="GitHub">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>

<!-- Modal de Checkout -->
<div id="checkoutModal" class="checkout-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Escolher Método de Pagamento</h3>
            <button class="close-button" onclick="closeCheckoutModal()">&times;</button>
        </div>
        
        <form id="checkoutForm" action="{{ route('pricing.checkout') }}" method="POST">
            @csrf
            <input type="hidden" id="selectedPlan" name="plan" value="">
            
            <div class="payment-methods">
                <label class="payment-method" onclick="selectPaymentMethod(this, 'credit_card')">
                    <input type="radio" name="payment_method" value="credit_card" class="sr-only">
                    <div class="payment-icon">💳</div>
                    <div class="payment-name">Cartão de Crédito</div>
                </label>
                
                <label class="payment-method" onclick="selectPaymentMethod(this, 'pix')">
                    <input type="radio" name="payment_method" value="pix" class="sr-only">
                    <div class="payment-icon">📱</div>
                    <div class="payment-name">PIX</div>
                </label>
                
                <label class="payment-method" onclick="selectPaymentMethod(this, 'debit_card')">
                    <input type="radio" name="payment_method" value="debit_card" class="sr-only">
                    <div class="payment-icon">💳</div>
                    <div class="payment-name">Débito</div>
                </label>
                
                <label class="payment-method" onclick="selectPaymentMethod(this, 'boleto')">
                    <input type="radio" name="payment_method" value="boleto" class="sr-only">
                    <div class="payment-icon">📄</div>
                    <div class="payment-name">Boleto</div>
                </label>
                
                <label class="payment-method" onclick="selectPaymentMethod(this, 'transfer')">
                    <input type="radio" name="payment_method" value="transfer" class="sr-only">
                    <div class="payment-icon">🏦</div>
                    <div class="payment-name">Transferência</div>
                </label>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCheckoutModal()">
                    Cancelar
                </button>
                <button type="submit" class="btn-primary">
                    Continuar para Pagamento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCheckoutModal(planName) {
    document.getElementById('selectedPlan').value = planName;
    document.getElementById('checkoutModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeCheckoutModal() {
    document.getElementById('checkoutModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    // Reset seleção
    document.querySelectorAll('.payment-method').forEach(method => {
        method.classList.remove('selected');
    });
}

function selectPaymentMethod(element, value) {
    // Remove seleção anterior
    document.querySelectorAll('.payment-method').forEach(method => {
        method.classList.remove('selected');
    });
    
    // Adiciona seleção atual
    element.classList.add('selected');
    
    // Marca o radio button
    const radio = element.querySelector('input[type="radio"]');
    radio.checked = true;
}

// Fechar modal ao clicar fora
document.getElementById('checkoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCheckoutModal();
    }
});

// Validar seleção de método de pagamento
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedMethod) {
        e.preventDefault();
        alert('Por favor, selecione um método de pagamento.');
        return;
    }
});

// Animações suaves
document.addEventListener('DOMContentLoaded', function() {
    // Animação de entrada dos cards
    const cards = document.querySelectorAll('.pricing-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>
@endsection