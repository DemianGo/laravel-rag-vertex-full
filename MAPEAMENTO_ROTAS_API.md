# 📋 MAPEAMENTO DE ROTAS: Laravel → FastAPI

## ❌ ROTAS QUE NÃO EXISTEM (Precisam ser criadas)

### 1. Document Ingestion (Upload)
**Frontend chama:**
- `/rag/ingest` (POST) → Arquivos, Texto, URL, Vídeo

**FastAPI disponível:**
- `/v1/extract/file` (POST) → Apenas extração, não cria documento
- `/v1/extract/url` (POST) → Apenas extração, não cria documento

**❌ FALTA:** Endpoint `/api/rag/ingest` que cria documentos no DB + gera chunks + embeddings

---

### 2. RAG Query/PHP Search
**Frontend chama:**
- `/rag/query` (POST) → Busca simples sem LLM
- `/rag/answer` (POST) → Busca + LLM (RAG tradicional)

**FastAPI disponível:**
- `/api/rag/python-search` (POST) → Busca Python com LLM

**❌ FALTA:** Endpoint `/api/rag/query` e `/api/rag/answer` compatíveis com Laravel

---

### 3. Document Chunks
**Frontend chama:**
- `/api/docs/{id}/chunks` (GET) → Lista chunks de um documento

**FastAPI disponível:**
- `/v1/user/docs/list` (GET) → Lista documentos (SEM chunks)

**❌ FALTA:** Endpoint para buscar chunks específicos

---

### 4. Video Processing
**Frontend chama:**
- `/api/video/ingest` (POST) → Upload/URL de vídeo

**FastAPI disponível:**
- ❌ Nenhum endpoint de vídeo

---

### 5. Excel Structured Query
**Frontend chama:**
- `/api/excel/query` (POST) → Query com agregações
- `/api/excel/{id}/structure` (GET) → Metadados estruturados

**FastAPI disponível:**
- ❌ Nenhum endpoint de Excel estruturado

---

### 6. Feedback System
**Frontend chama:**
- `/api/rag/feedback` (POST) → Enviar 👍👎
- `/api/rag/feedback/stats` (GET) → Estatísticas

**FastAPI disponível:**
- ❌ Nenhum endpoint de feedback

---

## ✅ ROTAS QUE JÁ FUNCIONAM

- `/v1/user/info` ✅
- `/v1/user/docs/list` ✅
- `/auth/register` ✅
- `/auth/login` ✅
- `/auth/api-key/*` ✅
- `/api/rag/python-search` ✅
- `/health` ✅

---

## 🎯 AÇÃO NECESSÁRIA

**Precisamos criar esses endpoints no FastAPI:**
1. `/api/rag/ingest` → Upload document + create chunks + embeddings
2. `/api/rag/query` → Simple search (PHP style)
3. `/api/rag/answer` → RAG + LLM (PHP style)
4. `/api/docs/{id}/chunks` → Get document chunks
5. `/api/video/ingest` → Video processing
6. `/api/excel/query` → Excel structured queries
7. `/api/excel/{id}/structure` → Excel metadata
8. `/api/rag/feedback` → Submit feedback
9. `/api/rag/feedback/stats` → Feedback statistics

**OU** ajustar `rag-client.js` para usar apenas endpoints que já existem.

---
