# 🏗️ Arquitetura do Sistema - Laravel RAG

## ⚠️ REGRA FUNDAMENTAL

**LARAVEL ≠ BACKEND de APIs**  
**FASTAPI = TODO o backend de processamento**

---

## 📊 Visão Geral

Este sistema usa arquitetura híbrida com separação clara de responsabilidades:

```
┌─────────────────────────────────────────────────────────────┐
│                     USUÁRIO FINAL                            │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ↓
        ┌──────────────────────────────────────┐
        │   NAVEGADOR (http://localhost:8000)  │
        └─────────────────┬────────────────────┘
                          │
          ┌───────────────┴───────────────┐
          │                               │
          ↓                               ↓
┌──────────────────┐          ┌──────────────────────┐
│  LARAVEL :8000   │          │  FASTAPI :8002       │
│  (Views + Auth)  │          │  (Todo Backend)      │
└──────────────────┘          └──────────────────────┘
```

---

## 🎯 LARAVEL (Porta 8000)

### ✅ O QUE LARAVEL FAZ:

1. **Views HTML**
   - Renderiza templates Blade
   - Serve arquivos estáticos (CSS, JS)
   - Frontend interativo

2. **Autenticação**
   - Login/Registro de usuários
   - Gestão de sessões web
   - Middleware de autenticação

3. **Admin Panel**
   - Dashboard administrativo
   - Gestão de usuários
   - Configurações do sistema

### ❌ O QUE LARAVEL NÃO FAZ:

- ❌ Processar uploads de documentos
- ❌ Executar buscas RAG
- ❌ Processar vídeos
- ❌ Gerar embeddings
- ❌ Qualquer operação de processamento de dados
- ❌ **Qualquer API de backend**

---

## 🚀 FASTAPI (Porta 8002)

### ✅ O QUE FASTAPI FAZ (TUDO):

1. **Upload de Documentos**
   - `POST /api/rag/ingest`
   - Recebe arquivos multipart
   - Chama scripts de extração Python
   - Armazena no PostgreSQL

2. **Listagem de Documentos**
   - `GET /api/docs/list`
   - Consulta PostgreSQL
   - Retorna documentos do usuário
   - Inclui metadados e contagem de chunks

3. **Busca RAG**
   - `POST /api/rag/python-search`
   - Chama `rag_search.py`
   - Busca vetorial + FTS
   - Integração com Gemini/OpenAI

4. **Processamento de Vídeos**
   - Download de vídeos
   - Transcrição automática
   - Armazenamento de chunks

5. **Geração de Embeddings**
   - Embeddings universais
   - Cache de resultados
   - Otimização de performance

### 📁 Arquivos FastAPI:

- `simple_rag_ingest.py` - Endpoint principal
- Scripts em `scripts/document_extraction/`
- Scripts em `scripts/rag_search/`
- Scripts em `scripts/video_processing/`

---

## 🔄 Fluxo de Dados

### **1. Upload de Documento:**

```
Frontend (Laravel View)
    ↓ [POST multipart/form-data]
FastAPI :8002/api/rag/ingest
    ↓
main_extractor.py
    ↓
PostgreSQL (documents, chunks)
    ↓ [JSON response]
Frontend atualiza UI
```

### **2. Busca RAG:**

```
Frontend (Laravel View)
    ↓ [POST JSON]
FastAPI :8002/api/rag/python-search
    ↓
rag_search.py
    ↓
PostgreSQL (chunks + embeddings)
    ↓
Gemini API
    ↓ [JSON response]
Frontend exibe resultados
```

### **3. Listagem de Documentos:**

```
Frontend (Laravel View)
    ↓ [GET]
FastAPI :8002/api/docs/list
    ↓
PostgreSQL (SELECT documents)
    ↓ [JSON response]
Frontend popula selects
```

---

## 🗄️ Banco de Dados (PostgreSQL)

**Gerido por:** FastAPI (Python scripts)

**Tabelas principais:**
- `documents` - Metadados dos documentos
- `chunks` - Chunks de texto + embeddings (vector)
- `users` - Usuários e autenticação
- `rag_feedbacks` - Analytics e feedbacks

---

## 🚫 O QUE NÃO FAZER

### ❌ ERRADO:
```php
// NÃO faça isso no Laravel:
Route::post('/api/rag/search', function() {
    // Processar busca RAG aqui
    // ❌ ERRADO!
});
```

### ✅ CORRETO:
```php
// Laravel apenas redireciona para FastAPI:
// No frontend, chamar diretamente:
fetch('http://localhost:8002/api/rag/search', ...)
```

---

## 📝 Resumo Executivo

| Componente | Porta | Responsabilidade |
|------------|-------|------------------|
| **Laravel** | 8000 | Views HTML + Login + Admin |
| **FastAPI** | 8002 | **TODO o processamento** |
| **PostgreSQL** | 5432 | Armazenamento de dados |

---

## ⚡ Comandos de Inicialização

```bash
# Terminal 1: Laravel (Frontend)
php artisan serve

# Terminal 2: FastAPI (Backend)
python3 simple_rag_ingest.py
```

---

**⚠️ LEMBRETE:** Nunca implemente lógica de backend no Laravel. Tudo vai para FastAPI!
