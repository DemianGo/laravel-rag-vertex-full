# Configuração do Mercado Pago

## Variáveis de Ambiente Necessárias

Adicione estas variáveis ao seu arquivo `.env`:

```env
# Mercado Pago Configuration
MERCADOPAGO_ACCESS_TOKEN=SEU_ACCESS_TOKEN_AQUI
MERCADOPAGO_PUBLIC_KEY=SEU_PUBLIC_KEY_AQUI
MERCADOPAGO_SANDBOX=true
MERCADOPAGO_WEBHOOK_SECRET=seu-webhook-secret
```

## Como Obter as Chaves

1. Acesse [mercadopago.com.br](https://www.mercadopago.com.br)
2. Faça login na sua conta
3. Vá em "Desenvolvedores" → "Suas integrações"
4. Copie o Access Token e Public Key
5. Para webhooks, configure a URL: `https://seudominio.com/webhook/mercadopago`

## Teste do Fluxo

1. Configure as variáveis de ambiente
2. Acesse `/precos`
3. Clique em "Escolher Plano"
4. Selecione um método de pagamento
5. Clique em "Continuar para Pagamento"
6. Você será redirecionado para o gateway do Mercado Pago

## Status Atual

✅ **Controller corrigido** - Não requer mais login obrigatório
✅ **MercadoPagoService funcionando** - Parâmetros corretos
✅ **Rotas configuradas** - Checkout, success, failure, pending
✅ **Frontend integrado** - Modal de pagamento funcional

## Próximos Passos

1. Configure as chaves do Mercado Pago no `.env`
2. Teste o fluxo completo de pagamento
3. Configure webhooks para receber notificações
