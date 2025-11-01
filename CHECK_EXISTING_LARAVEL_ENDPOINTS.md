# ✅ ENDPOINTS LARAVEL EXISTENTES (Para mapear para FastAPI)

## Endpoints que EXISTEM no Laravel mas NÃO existem no FastAPI

### 1. `/api/rag/ingest` (POST)
**Laravel:** `app/Http/Controllers/RagController.php::ingest()` (linha 167)
**Implementação:** Proxy para FastAPI (não está implementado!)
**Status:** ⚠️ Laravel chama FastAPI mas endpoint não existe no FastAPI

### 2. `/api/rag/query` (GET/POST)
**Laravel:** `app/Http/Controllers/RagController.php::query()`
**Status:** ✅ Implementação PHP completa

### 3. `/api/rag/answer` (GET/POST)
**Laravel:** `app/Http/Controllers/RagAnswerController.php::answer()`
**Status:** ✅ Implementação PHP completa

### 4. `/api/docs/{id}/chunks` (GET)
**Laravel:** `app/Http/Controllers/RagController.php::getDocumentChunks()` (linha 117)
**Status:** ✅ Implementação PHP completa

### 5. `/api/video/ingest` (POST)
**Laravel:** `app/Http/Controllers/VideoController.php::ingest()` (linha 26)
**Status:** ✅ Implementação PHP completa com `VideoProcessingService`

### 6. `/api/video/info` (POST)
**Laravel:** `app/Http/Controllers/VideoController.php::getInfo()`
**Status:** ✅ Implementação PHP completa

### 7. `/api/excel/query` (POST)
**Laravel:** `app/Http/Controllers/ExcelQueryController.php.disabled::query()` (linha 23)
**Status:** ⚠️ Controller DESABILITADO (.disabled)

### 8. `/api/excel/{id}/structure` (GET)
**Laravel:** `app/Http/Controllers/ExcelQueryController.php.disabled::getStructuredData()` (linha 85)
**Status:** ⚠️ Controller DESABILITADO (.disabled)

### 9. `/api/rag/feedback` (POST)
**Laravel:** `app/Http/Controllers/RagFeedbackController.php::store()` (linha 17)
**Status:** ✅ Implementação PHP completa

### 10. `/api/rag/feedback/stats` (GET)
**Laravel:** `app/Http/Controllers/RagFeedbackController.php::stats()` (linha 70)
**Status:** ✅ Implementação PHP completa

### 11. `/api/rag/feedback/recent` (GET)
**Laravel:** `app/Http/Controllers/RagFeedbackController.php::recent()`
**Status:** ✅ Implementação PHP completa

### 12. `/api/embeddings/generate` (POST)
**Laravel:** `app/Http/Controllers/UniversalEmbeddingController.php::generate()` (linha 33)
**Status:** ✅ Implementação PHP completa

### 13. `/api/embeddings/{id}/status` (GET)
**Laravel:** `app/Http/Controllers/UniversalEmbeddingController.php::status()` (linha 61)
**Status:** ✅ Implementação PHP completa

### 14. `/api/docs/list` (GET)
**Laravel:** `app/Http/Controllers/RagController.php::listDocs()` (linha 65)
**Status:** ✅ Implementação PHP completa

---

## 🎯 RESUMO

**Endpoints NÃO cobertos no FastAPI:**
- ❌ `/api/rag/ingest` (PROXY quebrado - Laravel chama FastAPI que não existe)
- ❌ `/api/rag/query` (PHP)
- ❌ `/api/rag/answer` (PHP)
- ❌ `/api/docs/{id}/chunks` (PHP)
- ❌ `/api/video/ingest` (PHP)
- ❌ `/api/video/info` (PHP)
- ❌ `/api/excel/query` (Controller .disabled)
- ❌ `/api/excel/{id}/structure` (Controller .disabled)
- ❌ `/api/rag/feedback` (PHP)
- ❌ `/api/rag/feedback/stats` (PHP)
- ❌ `/api/embeddings/generate` (PHP)

**Lógica Python existente (scripts):**
- ✅ `scripts/document_extraction/main_extractor.py` → Extração de documentos
- ✅ `scripts/rag_search/batch_embeddings.py` → Embeddings batch
- ✅ `scripts/video_processing/transcription_service.py` → Transcrição vídeos
- ✅ `scripts/document_extraction/excel_structured_extractor.py` → Excel estruturado

**SOLUÇÃO:** Integrar scripts Python existentes em endpoints FastAPI!
