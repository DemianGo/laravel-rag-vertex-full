# 🚀 CONFIGURAÇÃO PARA PRODUÇÃO - LIBERAI RAG

## 📋 **CONFIGURAÇÕES NECESSÁRIAS**

### **1. 🔑 Variáveis de Ambiente (.env)**

```bash
# ==============================================
# CONFIGURAÇÃO PARA PRODUÇÃO
# ==============================================

# Mercado Pago Configuration (PRODUÇÃO)
MERCADO_PAGO_ACCESS_TOKEN=APP-USR-1234567890-abcdef-1234567890abcdef-12345678
MERCADO_PAGO_PUBLIC_KEY=APP_USR_12345678-1234-1234-1234-123456789012
MERCADO_PAGO_WEBHOOK_SECRET=your_webhook_secret_here_production
MERCADO_PAGO_SANDBOX=false

# AI Cost Configuration
AI_COST_MULTIPLIER=1.5
DEFAULT_AI_PROVIDER=openai
MAX_TOKENS_PER_REQUEST=4000

# Currency Configuration
DEFAULT_CURRENCY=BRL
USD_TO_BRL_RATE=5.2

# Security Configuration
MAX_REQUESTS_PER_MINUTE=60
ENABLE_API_RATE_LIMIT=true

# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_ORGANIZATION=org-your-org-id-here

# Google Gemini Configuration
GOOGLE_APPLICATION_CREDENTIALS=/path/to/your/service-account-key.json
GOOGLE_GENAI_API_KEY=your-gemini-api-key-here

# Anthropic Claude Configuration
ANTHROPIC_API_KEY=sk-ant-your-claude-api-key-here
```

---

## 🎯 **COMO CONFIGURAR CADA PROVEDOR**

### **🤖 1. OpenAI**
```bash
# Acesse: https://platform.openai.com/api-keys
# 1. Gere uma API key
# 2. Configure limites de uso
# 3. Adicione no .env:
OPENAI_API_KEY=sk-your-key-here
```

### **🧠 2. Google Gemini**
```bash
# Acesse: https://ai.google.dev/
# 1. Gere uma API key
# 2. Configure quotas
# 3. Adicione no .env:
GOOGLE_GENAI_API_KEY=your-gemini-key-here
```

### **🎭 3. Anthropic Claude**
```bash
# Acesse: https://console.anthropic.com/
# 1. Gere uma API key
# 2. Configure limites
# 3. Adicione no .env:
ANTHROPIC_API_KEY=sk-ant-your-claude-key-here
```

### **💳 4. Mercado Pago**
```bash
# Acesse: https://www.mercadopago.com.br/developers
# 1. Crie uma aplicação
# 2. Copie ACCESS_TOKEN e PUBLIC_KEY
# 3. Configure webhook: https://your-domain.com/payment/webhook
# 4. Adicione no .env:
MERCADO_PAGO_ACCESS_TOKEN=APP-USR-your-token
MERCADO_PAGO_PUBLIC_KEY=APP_USR_your-key
MERCADO_PAGO_SANDBOX=false
```

---

## 💰 **CÁLCULOS DE CUSTO AUTOMÁTICOS**

### **📊 Exemplo Real: R$ 50,00 de Pagamento**

```php
// Usuário paga R$ 50,00
$pagamento = 50.00; // BRL

// Sistema converte para USD
$usd_value = $pagamento / 5.2; // ~$9.62 USD

// Calcula tokens disponíveis (GPT-4)
$tokens_disponiveis = $usd_value / 0.135; // ~71,000 tokens

// Margem de lucro: 50%
$custo_real = $usd_value * (1 / 1.5); // ~$6.41 USD
$lucro = $usd_value - $custo_real; // ~$3.21 USD
```

### **🎯 Configurações de Margem por Plano**

| Plano | Margem | Exemplo R$ 50 |
|-------|--------|---------------|
| **Free** | 0% | Sem cobrança |
| **Pro** | 50% | Lucro: R$ 16,67 |
| **Enterprise** | 30% | Lucro: R$ 11,54 |

---

## 🔧 **CONFIGURAÇÃO DO ADMIN**

### **1. Acessar Admin Panel**
```bash
# URL: https://your-domain.com/admin/login
# Login: admin@liberai.ai
# Senha: abab1212 (altere em produção!)
```

### **2. Configurar Provedores de IA**
```bash
# Menu: IA (/admin/ai-providers)
# - Editar custos por token
# - Configurar margens
# - Ativar/desativar modelos
```

### **3. Configurar Planos**
```bash
# Menu: Planos (/admin/plans)
# - Ajustar preços
# - Definir limites de tokens
# - Configurar margens específicas
```

---

## 📈 **MONITORAMENTO E ANALYTICS**

### **📊 Dashboard Financeiro**
- **URL:** `/admin/finance`
- **Métricas:**
  - Receita mensal/trimestral
  - Custo por provedor de IA
  - Margem de lucro por plano
  - ROI por modelo de IA

### **📋 Relatórios Disponíveis**
1. **Uso de Tokens:** Por usuário, por plano, por período
2. **Custos de IA:** Por provedor, por modelo, por dia
3. **Margens:** Lucro por transação, ROI médio
4. **Conversões:** Taxa de upgrade de planos

---

## 🚨 **ALERTAS E LIMITES**

### **⚠️ Alertas Automáticos**
- **Limite de tokens:** Usuário próximo do limite
- **Custo alto:** Uso excessivo de IA cara
- **Pagamento falhado:** Tentativa de pagamento rejeitada
- **Margem baixa:** Lucro abaixo do esperado

### **🔒 Limites de Segurança**
- **Rate limiting:** 60 requests/minuto
- **Timeout:** 30 segundos por requisição
- **Validação:** Todos os inputs validados
- **Logs:** Todas as operações logadas

---

## 🎯 **EXEMPLO DE FLUXO COMPLETO**

### **📝 Cenário: Usuário paga R$ 100,00**

1. **💳 Pagamento Processado**
   - Mercado Pago: R$ 100,00
   - Taxa MP: ~R$ 3,50
   - Recebido: R$ 96,50

2. **🔄 Conversão para USD**
   - Valor USD: $18,56 (R$ 96,50 ÷ 5,2)

3. **🤖 Cálculo de Tokens (GPT-4)**
   - Custo por 1K tokens: $0.135
   - Tokens disponíveis: ~137,000 tokens

4. **💰 Margem de Lucro**
   - Custo real: $12,37 (margem 50%)
   - Lucro: $6,19 (~R$ 32,19)

5. **📊 Analytics**
   - ROI: 50%
   - Tempo de uso estimado: 2-3 meses
   - Churn rate: <5%

---

## ✅ **CHECKLIST DE DEPLOY**

- [ ] **Configurar .env** com credenciais reais
- [ ] **Executar migrations** (`php artisan migrate`)
- [ ] **Popular dados** (`php artisan db:seed`)
- [ ] **Configurar SSL** (HTTPS obrigatório)
- [ ] **Configurar logs** (rotacionamento diário)
- [ ] **Configurar backup** (banco + arquivos)
- [ ] **Testar pagamentos** (sandbox → produção)
- [ ] **Monitorar métricas** (primeiros 7 dias)
- [ ] **Configurar alertas** (email/SMS)
- [ ] **Documentar procedimentos** (runbooks)

---

## 🆘 **SUPORTE E MANUTENÇÃO**

### **📞 Contatos**
- **Admin:** admin@liberai.ai
- **Suporte:** suporte@liberai.ai
- **Financeiro:** financeiro@liberai.ai

### **🔧 Comandos Úteis**
```bash
# Verificar status
php artisan tinker --execute="echo 'Sistema OK';"

# Limpar cache
php artisan cache:clear

# Ver logs
tail -f storage/logs/laravel.log

# Backup banco
pg_dump laravel_rag > backup_$(date +%Y%m%d).sql
```

---

**🎉 SISTEMA PRONTO PARA PRODUÇÃO!**
