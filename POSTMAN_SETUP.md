# 📮 GUIA DE SETUP DO POSTMAN - API KEYS

## 📥 PASSO 1: IMPORTAR ARQUIVOS NO POSTMAN

### 1.1. Importar Collection

1. Abra o Postman
2. Clique em **"Import"** (canto superior esquerdo)
3. Arraste o arquivo `postman_collection.json` OU clique em "Upload Files"
4. Selecione `postman_collection.json`
5. Clique em **"Import"**

✅ Você verá aparecer: **"Laravel RAG - API Keys"** na sidebar

### 1.2. Importar Environment

1. No Postman, clique no ícone de **"Environments"** (⚙️ no canto superior direito)
2. Clique em **"Import"**
3. Arraste o arquivo `postman_environment.json` OU clique em "Upload Files"
4. Selecione `postman_environment.json`
5. Clique em **"Import"**

✅ Você verá aparecer: **"Laravel RAG - Local Development"**

---

## 🔑 PASSO 2: GERAR API KEY

### 2.1. Via Terminal (Recomendado)

```bash
# 1. Criar usuário de teste (se não existir)
php artisan tinker --execute="
\$user = \\App\\Models\\User::firstOrCreate(['email' => 'postman@test.com'], [
    'name' => 'Postman Test User',
    'password' => bcrypt('senha123')
]);
\\App\\Models\\UserPlan::firstOrCreate(['user_id' => \$user->id], [
    'plan' => 'free',
    'tokens_limit' => 100,
    'documents_limit' => 1
]);
echo 'User ID: ' . \$user->id . PHP_EOL;
"

# 2. Gerar API key
php artisan api-keys:generate --user-id=1

# 3. COPIAR a API key gerada (exemplo):
# API Key: rag_0226e60b7140c4c08191f431dd2ddf33fef83490c0afbeb921c73861
```

### 2.2. Verificar Usuários Existentes

```bash
# Listar todos os usuários
php artisan tinker --execute="
\$users = \\App\\Models\\User::all(['id','name','email']);
foreach(\$users as \$u) {
    echo \"ID: \$u->id | \$u->name | \$u->email\" . PHP_EOL;
}
"

# Gerar API key para um usuário específico
php artisan api-keys:generate --user-id=<ID_DO_USUARIO>
```

---

## ⚙️ PASSO 3: CONFIGURAR ENVIRONMENT NO POSTMAN

### 3.1. Selecionar o Environment

1. No Postman, no canto superior direito, clique no dropdown de environments
2. Selecione **"Laravel RAG - Local Development"**

### 3.2. Configurar Variáveis

1. Clique no ícone de **"Environments"** (⚙️)
2. Clique em **"Laravel RAG - Local Development"**
3. Configure as variáveis:

| Variável | Valor | Descrição |
|----------|-------|-----------|
| `base_url` | `http://localhost:8000` | URL da API (já configurado) |
| `api_key` | `rag_0226e60b71...b861` | **COLE SUA API KEY AQUI** |
| `user_id` | `1` | ID do usuário (opcional) |
| `document_id` | `142` | ID do documento para testes (opcional) |

4. Clique em **"Save"** (ou Ctrl+S)

**⚠️ IMPORTANTE:** Cole a API key COMPLETA que você copiou do terminal!

---

## 🧪 PASSO 4: TESTAR AS REQUISIÇÕES

### 4.1. Ordem Recomendada de Testes

#### ✅ **Teste 1: Health Check**

Pasta: `1. Setup & Preparation` → `Health Check`

- **Não requer API key**
- Verifica se a API está online
- **Resposta esperada**: `{"ok": true, "ts": "..."}`

---

#### ✅ **Teste 2: Autenticação com Bearer Token**

Pasta: `2. API Key Authentication Tests` → `✅ Test Auth - Bearer Token (Valid)`

- **Requer API key** (já configurada no environment)
- Verifica autenticação funcionando
- **Resposta esperada**:
```json
{
  "success": true,
  "message": "API key is valid!",
  "user": {
    "id": 1,
    "name": "Postman Test User",
    "email": "postman@test.com"
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

---

#### ✅ **Teste 3: Autenticação com X-API-Key**

Pasta: `2. API Key Authentication Tests` → `✅ Test Auth - X-API-Key Header (Valid)`

- Mesmo teste anterior, mas usando header diferente
- **Resposta esperada**: Mesma do teste 2

---

#### ❌ **Teste 4: Falha sem API Key**

Pasta: `2. API Key Authentication Tests` → `❌ Test Auth - No API Key (Should Fail)`

- **Não tem API key** no header (propositalmente)
- **Status esperado**: 401 Unauthorized
- **Resposta esperada**:
```json
{
  "error": "API key required",
  "message": "Please provide an API key...",
  ...
}
```

---

#### ❌ **Teste 5: Falha com API Key Inválida**

Pasta: `2. API Key Authentication Tests` → `❌ Test Auth - Invalid API Key (Should Fail)`

- Usa uma API key **inválida** (propositalmente)
- **Status esperado**: 401 Unauthorized
- **Resposta esperada**:
```json
{
  "error": "Invalid API key",
  "message": "The provided API key is not valid."
}
```

---

#### ✅ **Teste 6: Listar Documentos**

Pasta: `3. RAG Endpoints (Protected)` → `List Documents`

- Lista todos os documentos disponíveis
- **Requer API key**
- **Resposta esperada**: Array de documentos

---

#### ✅ **Teste 7: Busca RAG**

Pasta: `3. RAG Endpoints (Protected)` → `RAG Python Search`

- Faz uma busca RAG usando o sistema Python
- **Requer API key**
- **Body já configurado** com exemplo
- **Resposta esperada**: Resultado da busca com resposta LLM

---

### 4.2. Cenários Completos

Pasta: `5. Test Scenarios`

- **Scenario 1: Full Auth Flow** - Fluxo completo de autenticação
- **Scenario 2: RAG Search with Auth** - Busca RAG autenticada

---

## 🔧 PASSO 5: TROUBLESHOOTING

### Problema 1: "API key required"

**Causa**: API key não configurada no environment

**Solução**:
1. Verifique se selecionou o environment correto (canto superior direito)
2. Verifique se a variável `{{api_key}}` está preenchida no environment
3. Clique em "Save" no environment após alterar

---

### Problema 2: "Invalid API key"

**Causa**: API key incorreta ou expirada

**Solução**:
1. Gere uma nova API key:
```bash
php artisan api-keys:generate --user-id=1 --force
```
2. Copie a nova key
3. Cole no environment do Postman
4. Clique em "Save"

---

### Problema 3: "Connection refused"

**Causa**: Servidor Laravel não está rodando

**Solução**:
```bash
# Iniciar o servidor
php artisan serve

# Ou usar o dev-start.sh
./dev-start.sh
```

---

### Problema 4: Variáveis não substituindo

**Causa**: Environment não selecionado

**Solução**:
1. Verifique se o environment está selecionado (canto superior direito)
2. Deve aparecer **"Laravel RAG - Local Development"**
3. Se estiver em **"No Environment"**, selecione o correto

---

## 📊 ESTRUTURA DA COLLECTION

```
Laravel RAG - API Keys
├── 1. Setup & Preparation
│   └── Health Check (não requer auth)
│
├── 2. API Key Authentication Tests
│   ├── ✅ Test Auth - Bearer Token (Valid)
│   ├── ✅ Test Auth - X-API-Key Header (Valid)
│   ├── ❌ Test Auth - No API Key (Should Fail)
│   └── ❌ Test Auth - Invalid API Key (Should Fail)
│
├── 3. RAG Endpoints (Protected)
│   ├── List Documents
│   ├── RAG Python Search
│   └── Get Feedback Stats
│
├── 4. API Key Management (Requires Session Auth)
│   ├── Get API Key Info (Masked)
│   ├── Generate New API Key
│   ├── Regenerate API Key
│   └── Revoke API Key
│   (⚠️ Nota: Estes endpoints requerem cookie de sessão, não API key)
│
└── 5. Test Scenarios
    ├── Scenario 1: Full Auth Flow
    └── Scenario 2: RAG Search with Auth
```

---

## ✅ CHECKLIST FINAL

Antes de começar a testar, verifique:

- [ ] Collection importada no Postman
- [ ] Environment importado no Postman
- [ ] Environment selecionado (canto superior direito)
- [ ] API key gerada via terminal
- [ ] API key colada no environment (variável `api_key`)
- [ ] Environment salvo (Ctrl+S)
- [ ] Servidor Laravel rodando (`php artisan serve`)
- [ ] Health Check funcionando

---

## 🎯 RESULTADO ESPERADO

Após seguir todos os passos:

- ✅ **4 testes devem PASSAR** (testes com ✅)
- ❌ **2 testes devem FALHAR propositalmente** (testes com ❌)
- ✅ **Endpoints RAG devem funcionar** com autenticação

---

## 📚 LINKS ÚTEIS

- **Documentação completa**: `TESTE_API_KEYS.md`
- **Status do projeto**: `.cursorrules`
- **Documentação técnica**: `PROJECT_README.md`

---

**✅ Setup completo! Bons testes!** 🚀

