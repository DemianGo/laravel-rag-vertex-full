# 📁 ESTRUTURA DE MÓDULOS PYTHON NO PROJETO

## Pastas Existentes com Código Python

### ✅ 1. `scripts/document_extraction/` (55 arquivos)
**Finalidade:** Extração de texto de documentos em múltiplos formatos
**Recursos:**
- PDF, DOCX, XLSX, PPTX, TXT, CSV, RTF, HTML, XML
- OCR avançado (Tesseract + Google Vision)
- Extração de tabelas
- Análise de qualidade
- Excel estruturado com JSON

**Arquivos Principais:**
- `main_extractor.py` - Orquestrador principal
- `excel_structured_extractor.py` - Queries estruturadas Excel
- `extractors/image_extractor.py` - OCR
- `quality/` - Análise de qualidade (10 arquivos)

**Endpoints para Criar:**
- ✅ POST `/api/rag/ingest` - Upload documentos (JÁ FEITO)
- ❌ POST `/api/excel/query` - Query estruturada Excel
- ❌ GET `/api/excel/{id}/structure` - Estrutura Excel

---

### ✅ 2. `scripts/video_processing/` (15 arquivos)
**Finalidade:** Processamento e transcrição de vídeos
**Recursos:**
- Download de 1000+ sites (YouTube, Vimeo, etc)
- Transcrição com Gemini/Google/OpenAI
- Extração de áudio com FFmpeg
- Limite: 60 minutos

**Arquivos Principais:**
- `video_downloader.py` - Download
- `transcription_service.py` - Transcrição
- `audio_extractor.py` - Extração áudio
- `working_final_transcriber.py` - Transcriber final

**Endpoints para Criar:**
- ❌ POST `/api/video/ingest` - Upload/URL vídeo
- ❌ POST `/api/video/info` - Info do vídeo

---

### ✅ 3. `scripts/rag_search/` (16 arquivos)
**Finalidade:** Busca RAG com embeddings + LLM
**Recursos:**
- Embeddings (all-mpnet-base-v2, 768d)
- Busca vetorial + FTS
- LLM Gemini/OpenAI
- Cache Redis/File
- Smart Router + Fallback

**Arquivos Principais:**
- `rag_search.py` - Busca principal
- `embeddings_service.py` - Embeddings
- `llm_service.py` - LLM
- `batch_embeddings.py` - Geração batch
- `hybrid_search_service.py` - Busca híbrida

**Endpoints Criados:**
- ✅ POST `/api/rag/python-search`
- ✅ POST `/api/rag/ingest`
- ✅ POST `/api/rag/embeddings/generate`
- ✅ GET `/api/rag/docs/{id}/chunks`

**Endpoints Pendentes:**
- ❌ POST `/api/rag/feedback` - Feedback
- ❌ GET `/api/rag/feedback/stats` - Estatísticas

---

### ✅ 4. `scripts/api/` (28 arquivos)
**Finalidade:** FastAPI Enterprise com routers
**Estrutura:**
```
api/
├── main.py (aplicação principal)
├── core/ (config, security, logging)
├── middleware/ (auth, rate_limit, request_id)
├── models/ (requests, responses, enums)
├── routers/ (auth, user, rag, extraction, batch, admin, health)
├── services/ (cache, extractor, metrics, notification)
└── utils/ (file_utils, validators)
```

**Routers Existentes:**
- ✅ `auth.py` - Autenticação
- ✅ `user.py` - Usuários
- ✅ `rag.py` - RAG (JÁ ATUALIZADO COM ENDPOINTS NOVOS)
- ✅ `extraction.py` - Extração genérica
- ✅ `batch.py` - Batch processing
- ✅ `admin.py` - Admin
- ✅ `health.py` - Health checks

**Routers para Criar:**
- ❌ `video.py` - Vídeos
- ❌ `excel.py` - Excel estruturado
- ❌ `feedback.py` - Feedback RAG

---

### ⚠️ 5. `scripts/pdf_extraction/` (2 arquivos)
**Finalidade:** Extração PDF legada
**Status:** Pode ser removido (substituído por `document_extraction/`)

---

## 🎯 RESUMO DE ENDPOINTS PENDENTES

### Router `video.py` (2 endpoints)
```python
POST /api/video/ingest
  - Upload local ou URL
  - Processa vídeo
  - Cria documento + chunks
  - Script: video_processing/working_final_transcriber.py

POST /api/video/info
  - Retorna info do vídeo
  - Script: video_processing/video_downloader.py
```

### Router `excel.py` (2 endpoints)
```python
POST /api/excel/query
  - Query estruturada com agregações
  - Script: document_extraction/excel_structured_extractor.py

GET /api/excel/{id}/structure
  - Estrutura/metadados
  - Script: document_extraction/excel_structured_extractor.py
```

### Adicionar em `rag.py` (3 endpoints)
```python
POST /api/rag/feedback
GET /api/rag/feedback/stats
GET /api/rag/feedback/recent
  - Tabela: rag_feedbacks
```

---

## 📋 CHECKLIST DE CRIAÇÃO

### Fase 1: Criar routers (AGORA)
- [ ] Criar `scripts/api/routers/video.py`
- [ ] Criar `scripts/api/routers/excel.py`
- [ ] Adicionar feedback endpoints em `rag.py`

### Fase 2: Incluir no main.py
- [ ] Importar routers
- [ ] `app.include_router(video.router)`
- [ ] `app.include_router(excel.router)`

### Fase 3: Testar com Postman
- [ ] Testar `/api/video/ingest`
- [ ] Testar `/api/excel/query`
- [ ] Testar `/api/rag/feedback`

---

## 🔒 PERMISSÕES E ARQUITETURA

**Padrão:**
- Cada módulo = 1 router
- Cada router importa scripts de sua pasta
- Mantém separação de responsabilidades
- Facilita revogação de permissões no futuro

**Exemplo:**
```
scripts/api/routers/video.py
  → importa scripts/video_processing/*.py
  → endpoints: /api/video/*
  → Permissão: read-only

scripts/api/routers/excel.py
  → importa scripts/document_extraction/excel_*.py
  → endpoints: /api/excel/*
  → Permissão: read-only
```
