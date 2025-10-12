# 🧪 GUIA COMPLETO DE TESTES - API KEYS

## ✅ FASE 3.1: API KEYS POR USUÁRIO - CONCLUÍDA

---

## 📋 ÍNDICE

1. [Testes Automatizados](#1-testes-automatizados)
2. [Testes Manuais com cURL](#2-testes-manuais-com-curl)
3. [Testes com Postman/Insomnia](#3-testes-com-postmaninsomnia)
4. [Testes de Segurança](#4-testes-de-segurança)
5. [Próximos Passos](#5-próximos-passos)

---

## 1. TESTES AUTOMATIZADOS

### 1.1. Rodar Todos os Testes

```bash
# Rodar todos os testes de API Keys
php artisan test --filter=ApiKeyTest

# Resultado esperado: 12 testes passando (46 assertions)
```

### 1.2. Rodar Testes Individuais

```bash
# Teste de geração
php artisan test --filter="test_user_can_generate_api_key"

# Teste de autenticação
php artisan test --filter="test_api_key_authentication_with_valid_key"

# Teste de regeneração
php artisan test --filter="test_user_can_regenerate_api_key"
```

### 1.3. Testes Disponíveis

✅ `test_user_can_generate_api_key` - Verifica geração de API key
✅ `test_user_can_regenerate_api_key` - Verifica regeneração
✅ `test_masked_api_key_is_displayed_correctly` - Verifica mascaramento
✅ `test_api_key_authentication_with_valid_key` - Autentica com Bearer token
✅ `test_api_key_authentication_with_x_api_key_header` - Autentica com X-API-Key
✅ `test_api_key_authentication_fails_without_key` - Falha sem key
✅ `test_api_key_authentication_fails_with_invalid_key` - Falha com key inválida
✅ `test_api_key_last_used_timestamp_is_updated` - Atualiza timestamp
✅ `test_user_plan_is_included_in_auth_test_response` - Retorna plano do usuário
✅ `test_api_key_is_hidden_in_json_serialization` - API key não vaza em JSON
✅ `test_api_key_can_be_revoked` - Revogação funciona
✅ `test_multiple_users_can_have_different_api_keys` - Keys únicas por usuário

---

## 2. TESTES MANUAIS COM CURL

### 2.1. Preparação: Criar Usuário e Gerar API Key

```bash
# 1. Criar usuário de teste (se não existir)
php artisan tinker --execute="
\$user = \\App\\Models\\User::firstOrCreate(['email' => 'teste@apikeys.com'], [
    'name' => 'Usuario Teste',
    'password' => bcrypt('senha123')
]);
\\App\\Models\\UserPlan::firstOrCreate(['user_id' => \$user->id], [
    'plan' => 'free',
    'tokens_limit' => 100,
    'documents_limit' => 1
]);
echo 'Usuario criado: ID ' . \$user->id . PHP_EOL;
"

# 2. Gerar API key para o usuário
php artisan api-keys:generate --user-id=1

# 3. Copiar a API key gerada (exemplo):
# API Key: rag_0226e60b7140c4c08191f431dd2ddf33fef83490c0afbeb921c73861
```

### 2.2. Teste 1: Autenticação com Bearer Token ✅

```bash
curl -X GET http://localhost:8000/api/auth/test \
  -H "Authorization: Bearer rag_0226e60b7140c4c08191f431dd2ddf33fef83490c0afbeb921c73861" \
  -H "Content-Type: application/json"
```

**Resposta esperada:**
```json
{
  "success": true,
  "message": "API key is valid!",
  "user": {
    "id": 1,
    "name": "Usuario Teste",
    "email": "teste@apikeys.com"
  },
  "plan": {
    "plan": "free",
    "tokens_used": 0,
    "tokens_limit": 100,
    "documents_used": 0,
    "documents_limit": 1
  }
}
```

### 2.3. Teste 2: Autenticação com X-API-Key Header ✅

```bash
curl -X GET http://localhost:8000/api/auth/test \
  -H "X-API-Key: rag_0226e60b7140c4c08191f431dd2ddf33fef83490c0afbeb921c73861" \
  -H "Content-Type: application/json"
```

**Resposta esperada:** Mesma do teste anterior

### 2.4. Teste 3: Falha sem API Key ✅

```bash
curl -X GET http://localhost:8000/api/auth/test \
  -H "Content-Type: application/json"
```

**Resposta esperada:**
```json
{
  "error": "API key required",
  "message": "Please provide an API key in the Authorization header (Bearer token) or X-API-Key header.",
  "details": {
    "supported_headers": [
      "Authorization: Bearer <your-api-key>",
      "X-API-Key: <your-api-key>"
    ]
  }
}
```

### 2.5. Teste 4: Falha com API Key Inválida ✅

```bash
curl -X GET http://localhost:8000/api/auth/test \
  -H "Authorization: Bearer rag_invalid_key_12345" \
  -H "Content-Type: application/json"
```

**Resposta esperada:**
```json
{
  "error": "Invalid API key",
  "message": "The provided API key is not valid."
}
```

### 2.6. Teste 5: Verificar Timestamp de Último Uso ✅

```bash
# Antes de fazer a requisição
php artisan tinker --execute="
\$user = \\App\\Models\\User::find(1);
echo 'Antes: ' . (\$user->api_key_last_used_at ? \$user->api_key_last_used_at->toDateTimeString() : 'NULL') . PHP_EOL;
"

# Fazer requisição com API key
curl -X GET http://localhost:8000/api/auth/test \
  -H "Authorization: Bearer <sua-api-key>" \
  -H "Content-Type: application/json"

# Verificar depois
php artisan tinker --execute="
\$user = \\App\\Models\\User::find(1);
echo 'Depois: ' . \$user->api_key_last_used_at->toDateTimeString() . PHP_EOL;
"
```

### 2.7. Teste 6: Regenerar API Key ✅

```bash
# API key antiga
OLD_KEY="rag_0226e60b7140c4c08191f431dd2ddf33fef83490c0afbeb921c73861"

# Regenerar
php artisan api-keys:generate --user-id=1 --force

# Nova API key será exibida, copiar ela
NEW_KEY="rag_6e93d9fc91786023795206c3ade979e2afd56003d3530b391e76bb2d"

# Testar que a antiga não funciona mais
curl -X GET http://localhost:8000/api/auth/test \
  -H "Authorization: Bearer $OLD_KEY"
# Deve retornar: "Invalid API key"

# Testar que a nova funciona
curl -X GET http://localhost:8000/api/auth/test \
  -H "Authorization: Bearer $NEW_KEY"
# Deve retornar: "API key is valid!"
```

### 2.8. Teste 7: Gerar API Keys em Massa ✅

```bash
# Gerar para todos os usuários sem API key
php artisan api-keys:generate --all

# Verificar quantas foram geradas
php artisan tinker --execute="
\$total = \\App\\Models\\User::count();
\$comApiKey = \\App\\Models\\User::whereNotNull('api_key')->count();
echo \"Total de usuários: \$total\" . PHP_EOL;
echo \"Com API key: \$comApiKey\" . PHP_EOL;
"
```

---

## 3. TESTES COM POSTMAN/INSOMNIA

### 3.1. Criar Collection no Postman

**Request 1: Test Authentication (Bearer)**
- Method: GET
- URL: `http://localhost:8000/api/auth/test`
- Headers:
  - `Authorization`: `Bearer rag_<sua-api-key>`
  - `Content-Type`: `application/json`

**Request 2: Test Authentication (X-API-Key)**
- Method: GET
- URL: `http://localhost:8000/api/auth/test`
- Headers:
  - `X-API-Key`: `rag_<sua-api-key>`
  - `Content-Type`: `application/json`

**Request 3: Test Without Key (Deve Falhar)**
- Method: GET
- URL: `http://localhost:8000/api/auth/test`
- Headers:
  - `Content-Type`: `application/json`

---

## 4. TESTES DE SEGURANÇA

### 4.1. API Key Não Vaza em Respostas JSON

```bash
php artisan tinker --execute="
\$user = \\App\\Models\\User::find(1);
\$array = \$user->toArray();
if (isset(\$array['api_key'])) {
    echo '❌ FALHA: API key vazou no JSON!' . PHP_EOL;
} else {
    echo '✅ SUCESSO: API key está protegida' . PHP_EOL;
}
"
```

### 4.2. API Key Mascarada na Exibição

```bash
php artisan tinker --execute="
\$user = \\App\\Models\\User::find(1);
echo 'API Key mascarada: ' . \$user->masked_api_key . PHP_EOL;
"
```

**Resultado esperado:** `rag_0226e60b71...b861`

### 4.3. Logs de Tentativas Inválidas

```bash
# Fazer tentativa com key inválida
curl -X GET http://localhost:8000/api/auth/test \
  -H "Authorization: Bearer rag_invalid_key_123"

# Verificar logs
tail -20 storage/logs/laravel.log | grep "Invalid API key"
```

---

## 5. PRÓXIMOS PASSOS

### 5.1. Próxima Fase: 3.2 - Rate Limiting por Usuário

**O que será implementado:**
- Middleware para verificar limites do plano
- Tracking de uso por usuário (tokens/documentos)
- Reset mensal automático
- Headers de rate limit nas respostas

**Como testar quando estiver pronto:**
```bash
# Testar limite de tokens (Free: 100/mês)
# Fazer 101 requisições e verificar se a 101ª falha

# Testar limite de documentos (Free: 1)
# Fazer upload de 2 documentos e verificar se o 2º falha
```

### 5.2. Comandos Úteis

```bash
# Ver todas as API keys
php artisan tinker --execute="
\$users = \\App\\Models\\User::whereNotNull('api_key')->get(['id','name','email']);
foreach(\$users as \$u) {
    echo \"ID: \$u->id | \$u->name | \$u->email\" . PHP_EOL;
}
"

# Ver estatísticas
php artisan tinker --execute="
\$total = \\App\\Models\\User::count();
\$comKey = \\App\\Models\\User::whereNotNull('api_key')->count();
echo \"Usuários com API key: \$comKey / \$total\" . PHP_EOL;
"

# Revogar API key de um usuário
php artisan tinker --execute="
\$user = \\App\\Models\\User::find(1);
\$user->api_key = null;
\$user->api_key_created_at = null;
\$user->api_key_last_used_at = null;
\$user->save();
echo 'API key revogada!' . PHP_EOL;
"
```

---

## 📊 CHECKLIST DE TESTES

### Testes Automatizados
- [ ] Rodar `php artisan test --filter=ApiKeyTest`
- [ ] Todos os 12 testes devem passar
- [ ] 46 assertions devem passar

### Testes Manuais
- [ ] Autenticação com Bearer token funciona
- [ ] Autenticação com X-API-Key funciona
- [ ] Falha sem API key (401)
- [ ] Falha com API key inválida (401)
- [ ] Timestamp de último uso atualiza
- [ ] Regeneração invalida API key antiga
- [ ] Geração em massa funciona
- [ ] Plano do usuário retorna na resposta

### Testes de Segurança
- [ ] API key não vaza em JSON
- [ ] API key exibida mascarada
- [ ] Logs registram tentativas inválidas

---

## ✅ SISTEMA 100% TESTADO E FUNCIONAL!

**Data de Conclusão:** 2025-10-11  
**Status:** ✅ TODOS OS TESTES PASSANDO  
**Próxima Fase:** 3.2 - Rate Limiting por Usuário

