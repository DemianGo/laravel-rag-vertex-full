# Sistema de Autenticação FastAPI - Completo

## ✅ Endpoints Criados

### Autenticação Pública (sem API key)
- **POST /auth/register** - Registra novo usuário e gera API key automaticamente
- **POST /auth/login** - Login de usuário, retorna API key (gera se não existir)

### Gerenciamento de API Keys (requer autenticação)
- **POST /auth/api-key/generate** - Gera nova API key
- **POST /auth/api-key/regenerate** - Regenera API key (invalida a antiga)
- **GET /auth/api-key** - Obtém API key atual
- **DELETE /auth/api-key/revoke** - Revoga (deleta) API key

## 🔑 Formato da API Key

**Formato**: `rag_` + 56 caracteres hexadecimais
**Exemplo**: `rag_1a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p7q8r9s0t1u2v3w4x5y6z7a8b`

**Compatível com Laravel**: Mesmo formato usado em `User::generateApiKey()`:
```php
$apiKey = 'rag_' . bin2hex(random_bytes(28)); // 56 hex chars + prefix = 60 chars
```

**Python equivalente**:
```python
random_part = secrets.token_hex(28)  # 28 bytes = 56 hex characters
api_key = f"rag_{random_part}"
```

## 📦 Arquivos Criados/Modificados

1. **`/var/www/html/laravel-rag-vertex-full/scripts/api/routers/auth.py`** - Router completo de autenticação
   - Registro de usuários
   - Login
   - Geração de API keys
   - Gerenciamento de API keys

2. **`/var/www/html/laravel-rag-vertex-full/scripts/api/main.py`** - Router auth incluído
   - Registrado em: `app.include_router(auth.router)`

3. **`/var/www/html/laravel-rag-vertex-full/scripts/api/middleware/auth.py`** - Endpoints públicos excluídos
   - `/auth/register` - público
   - `/auth/login` - público

4. **`/var/www/html/laravel-rag-vertex-full/postman_collection_rag_api.json`** - Coleção atualizada
   - Seção "Authentication" adicionada com todos os endpoints
   - Descrição de como obter API key atualizada

## 🔐 Segurança

- ✅ Senhas hasheadas com bcrypt (compatível com Laravel)
- ✅ Fallback para hash simples (apenas durante migração)
- ✅ API keys no mesmo formato do Laravel
- ✅ Validação de email duplicado
- ✅ Controle de usuários inativos
- ✅ Tenant isolation automático (`tenant_slug = user_{user_id}`)

## 📋 Fluxo de Uso

### Novo Usuário:
1. **POST /auth/register** com `name`, `email`, `password`
2. Sistema cria usuário, gera tenant_slug e API key automaticamente
3. Resposta inclui API key no formato `rag_...`
4. Use essa API key em todos os outros endpoints

### Usuário Existente:
1. **POST /auth/login** com `email`, `password`
2. Sistema valida credenciais
3. Se usuário não tiver API key, gera automaticamente
4. Resposta inclui API key
5. Use essa API key em todos os outros endpoints

### Gerar Nova API Key:
1. Use API key atual no header `X-API-Key`
2. **POST /auth/api-key/generate** ou **POST /auth/api-key/regenerate**
3. Nova API key é retornada (antiga é invalidada se regenerar)
4. Use a nova API key a partir de agora

## 🧪 Testes com Postman

1. Importe `postman_collection_rag_api.json`
2. Teste **Register User** primeiro (cria conta e obtém API key)
3. Copie a API key retornada
4. Configure variável `api_key` na coleção Postman
5. Teste outros endpoints usando a API key

## ⚠️ Dependências

- **bcrypt**: Para hash de senhas (compatível com Laravel)
  ```bash
  pip install bcrypt
  ```
- **secrets**: Biblioteca padrão Python (já incluída)

## 🎯 Multi-Tenant

- Cada usuário recebe `tenant_slug = user_{user_id}` automaticamente
- Todos os documentos são isolados por tenant_slug
- Sistema suporta milhares de usuários simultaneamente

## ✅ Compatibilidade com Laravel

- Formato de API key: ✅ idêntico
- Formato de hash de senha: ✅ bcrypt (compatível)
- Estrutura de banco: ✅ mesma tabela `users`
- Tenant isolation: ✅ mesmo formato `user_{user_id}`
