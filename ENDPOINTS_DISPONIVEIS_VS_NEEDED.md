# 📊 ENDPOINTS: Disponíveis vs Necessários

## ✅ ENDPOINTS QUE JÁ EXISTEM

### FastAPI (`/api`, `/v1`)
1. **Auth** (`/auth/*`)
   - `/auth/register` ✅
   - `/auth/login` ✅
   - `/auth/api-key/generate` ✅
   - `/auth/api-key/regenerate` ✅
   - `/auth/api-key` (GET) ✅
   - `/auth/api-key/revoke` ✅

2. **User** (`/v1/user/*`)
   - `/v1/user/info` ✅
   - `/v1/user/docs/list` ✅
   - `/v1/user/test` ✅
   - `/v1/user/health` ✅

3. **RAG** (`/api/rag/*`)
   - `/api/rag/python-search` ✅
   - `/api/rag/python-health` ✅

4. **Health** (`/health`, `/ready`, `/live`)
   - `/health` ✅
   - `/health/simple` ✅
   - `/ready` ✅
   - `/live` ✅

5. **Extraction** (`/v1/extract/*`)
   - `/v1/extract/file` ✅ (extração apenas)
   - `/v1/extract/url` ✅ (extração apenas)

6. **Batch** (`/v1/batch/*`)
   - `/v1/batch/extract` ✅

7. **Admin** (`/v1/admin/*`)
   - `/v1/admin/cache/clear` ✅
   - `/v1/admin/jobs/cleanup` ✅
   - `/v1/admin/stats` ✅
   - `/v1/admin/formats` ✅

---

## ❌ ENDPOINTS QUE FALTAM

### 1. Document Ingestion `/api/rag/ingest`
**Status:** ❌ Não existe (Laravel proxy chama mas não existe)
**Precisa de:**
- Upload de arquivo (PDF, DOCX, etc)
- Texto direto
- URL
- Vídeo (URL ou upload)
- Criar documento no DB
- Criar chunks no DB
- **Gerar embeddings** (batch)
- Retornar document_id e chunks_created

**Script disponível:** `simple_rag_ingest.py` (root) - mas não gera embeddings!

---

### 2. Get Document Chunks `/api/docs/{id}/chunks`
**Status:** ❌ Não existe
**Precisa retornar:**
```json
[
  {"id": 1, "content": "...", "ord": 0, "meta": {}},
  {"id": 2, "content": "...", "ord": 1, "meta": {}}
]
```

**Script disponível:** `scripts/rag_search/database.py::get_chunks_with_embeddings`

---

### 3. Video Processing `/api/video/ingest`
**Status:** ❌ Não existe
**Precisa:**
- Upload local OU URL
- Transcrição (Gemini/Google/OpenAI)
- Criar documento no DB
- Criar chunks da transcrição
- Gerar embeddings

**Scripts disponíveis:**
- `scripts/video_processing/video_downloader.py`
- `scripts/video_processing/transcription_service.py`

---

### 4. Excel Structured `/api/excel/query` e `/api/excel/{id}/structure`
**Status:** ❌ Não existe
**Precisa:**
- Query com agregações (SUM, AVG, etc)
- Estrutura/metadados

**Script disponível:** `scripts/document_extraction/excel_structured_extractor.py`

---

### 5. Feedback `/api/rag/feedback` e `/api/rag/feedback/stats`
**Status:** ❌ Não existe
**Precisa:**
- POST: enviar 👍👎
- GET: estatísticas

**Tabela:** `rag_feedbacks`

---

### 6. PHP Compatibility `/api/rag/query` e `/api/rag/answer`
**Status:** ❌ Não existe
**Precisa:** Compatibilidade com frontend Laravel
- `/query` = busca simples (sem LLM)
- `/answer` = RAG + LLM (tradicional)

**Opção:** Usar `/api/rag/python-search` com parâmetros diferentes

---

## 🎯 PLANO DE IMPLEMENTAÇÃO

### FASE 1: Ingestion Básico (Agora)
1. Criar `/api/rag/ingest` no `scripts/api/routers/rag.py`
2. Integrar `main_extractor.py` para extração
3. Usar `batch_embeddings.py` para embeddings
4. Testar upload completo

### FASE 2: Endpoints Complementares
5. Criar `/api/docs/{id}/chunks`
6. Criar `/api/video/ingest`
7. Criar `/api/excel/query` e `/api/excel/{id}/structure`
8. Criar `/api/rag/feedback` e `/api/rag/feedback/stats`

### FASE 3: Compatibilidade PHP
9. Criar `/api/rag/query` e `/api/rag/answer` compatíveis

---

## ⚡ DECISÃO AGORA

**Opção A:** Implementar TUDO de uma vez (pode quebrar)
**Opção B:** Implementar Fase 1 (ingest) → Testar → Continuar
**Opção C:** Mapear frontend para usar endpoints existentes

**Qual você prefere?**
