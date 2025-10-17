# API Documentation - Laravel RAG System

## 🎯 **Visão Geral**

A API do Laravel RAG System oferece endpoints completos para upload de documentos, geração de embeddings, busca RAG e processamento de vídeos. Todos os endpoints são protegidos por autenticação dual (web sessions + API tokens).

## 🔐 **Autenticação**

### **Métodos de Autenticação**

#### **1. Web Sessions (Recomendado para Frontend)**
```javascript
// Headers necessários
{
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "csrf_token_here"
}

// Cookies de sessão são enviados automaticamente
credentials: 'same-origin'
```

#### **2. API Tokens (Para Integrações)**
```javascript
// Headers necessários
{
  "Content-Type": "application/json",
  "Authorization": "Bearer rag_<56_hex_chars>"
}
```

### **Obter API Key**
```bash
# Gerar nova API key para usuário
php artisan api-keys:generate --user-id=1

# Endpoint para obter API key atual
GET /api/user/api-key
```

## 📚 **Endpoints de Documentos**

### **Listar Documentos**
```http
GET /api/docs/list
```

**Resposta:**
```json
{
  "success": true,
  "docs": [
    {
      "id": 1,
      "title": "Documento.pdf",
      "source": "file_upload",
      "created_at": "2025-10-17T10:00:00Z",
      "chunks_count": 15,
      "metadata": {
        "file_size": 1024000,
        "pages": 5
      }
    }
  ],
  "total": 1
}
```

### **Obter Documento Específico**
```http
GET /api/docs/{id}
```

**Resposta:**
```json
{
  "success": true,
  "document": {
    "id": 1,
    "title": "Documento.pdf",
    "source": "file_upload",
    "uri": "storage/documents/1/documento.pdf",
    "tenant_slug": "user_1",
    "metadata": {
      "file_size": 1024000,
      "pages": 5,
      "language": "pt",
      "extraction_method": "pdf_pdftotext"
    },
    "created_at": "2025-10-17T10:00:00Z"
  }
}
```

### **Obter Chunks do Documento**
```http
GET /api/docs/{id}/chunks
```

**Resposta:**
```json
{
  "success": true,
  "chunks": [
    {
      "id": 1,
      "content": "Conteúdo do chunk...",
      "chunk_index": 0,
      "metadata": {
        "page": 1,
        "section": "introduction"
      }
    }
  ],
  "total": 15
}
```

## 📤 **Upload de Documentos**

### **Upload Individual**
```http
POST /api/rag/ingest
Content-Type: multipart/form-data
```

**Parâmetros:**
- `files[]` (file): Arquivo(s) para upload
- `url` (string, opcional): URL para download
- `text` (string, opcional): Texto direto
- `metadata` (JSON, opcional): Metadados adicionais
- `_token` (string): CSRF token

**Resposta:**
```json
{
  "ok": true,
  "document_id": 1,
  "title": "Documento.pdf",
  "chunks_created": 15,
  "processing_time": 2.5,
  "extraction_method": "pdf_pdftotext",
  "language_detected": "pt",
  "quality_metrics": {
    "text_quality": 0.95,
    "completeness": 0.98
  }
}
```

### **Upload em Lote**
```http
POST /api/rag/bulk-ingest
Content-Type: multipart/form-data
```

**Parâmetros:**
- `files[]` (file[]): Múltiplos arquivos
- `_token` (string): CSRF token

**Resposta:**
```json
{
  "success": true,
  "results": [
    {
      "index": 0,
      "success": true,
      "document_id": 1,
      "title": "Doc1.pdf",
      "chunks_created": 10
    },
    {
      "index": 1,
      "success": false,
      "error": "Formato não suportado"
    }
  ],
  "success_count": 1,
  "fail_count": 1
}
```

## 🔍 **Busca RAG**

### **Busca RAG Python (Recomendado)**
```http
POST /api/rag/python-search
Content-Type: application/json
```

**Parâmetros:**
```json
{
  "query": "Sobre o que trata este documento?",
  "document_id": 1,
  "top_k": 5,
  "threshold": 0.3,
  "include_answer": true,
  "strictness": 2,
  "mode": "auto",
  "format": "plain",
  "length": "auto",
  "citations": 0,
  "use_full_document": false,
  "use_smart_mode": true,
  "_token": "csrf_token"
}
```

**Resposta:**
```json
{
  "success": true,
  "query": "Sobre o que trata este documento?",
  "answer": "Este documento trata sobre...",
  "chunks": [
    {
      "id": 1,
      "content": "Conteúdo relevante...",
      "similarity": 0.95,
      "document_id": 1,
      "metadata": {
        "page": 1
      }
    }
  ],
  "metadata": {
    "method_used": "vector_search",
    "execution_time": 1.2,
    "total_chunks_searched": 100,
    "cache_hit": false
  }
}
```

### **Busca RAG PHP (Fallback)**
```http
POST /api/rag/query
Content-Type: application/json
```

**Parâmetros:**
```json
{
  "query": "Busca por texto",
  "document_id": 1,
  "top_k": 5,
  "_token": "csrf_token"
}
```

**Resposta:**
```json
{
  "ok": true,
  "answer": "Resposta baseada em busca por texto...",
  "used_chunks": 3,
  "method": "text_search_fts",
  "execution_time": 0.5
}
```

## 🎥 **Processamento de Vídeos**

### **Upload/URL de Vídeo**
```http
POST /api/video/ingest
Content-Type: multipart/form-data
```

**Parâmetros:**
- `video_file` (file, opcional): Arquivo de vídeo
- `video_url` (string, opcional): URL do vídeo
- `_token` (string): CSRF token

**Resposta:**
```json
{
  "ok": true,
  "document_id": 1,
  "title": "Video.mp4",
  "transcription": "Transcrição completa do vídeo...",
  "chunks_created": 25,
  "metadata": {
    "duration": 300,
    "resolution": "1920x1080",
    "transcription_service": "gemini",
    "language": "pt"
  }
}
```

### **Informações do Vídeo**
```http
POST /api/video/info
Content-Type: application/json
```

**Parâmetros:**
```json
{
  "video_url": "https://youtube.com/watch?v=...",
  "_token": "csrf_token"
}
```

**Resposta:**
```json
{
  "ok": true,
  "info": {
    "title": "Título do Vídeo",
    "duration": 300,
    "thumbnail": "https://...",
    "description": "Descrição do vídeo"
  }
}
```

## 📊 **Excel Estruturado**

### **Query em Excel**
```http
POST /api/excel/query
Content-Type: application/json
```

**Parâmetros:**
```json
{
  "document_id": 1,
  "query": "Qual a soma da coluna 'Valor'?",
  "_token": "csrf_token"
}
```

**Resposta:**
```json
{
  "success": true,
  "result": "R$ 15.000,00",
  "method": "aggregation",
  "aggregation_type": "SUM",
  "column": "Valor",
  "execution_time": 0.3
}
```

### **Estrutura do Excel**
```http
GET /api/excel/{id}/structure
```

**Resposta:**
```json
{
  "success": true,
  "structure": {
    "sheets": [
      {
        "name": "Sheet1",
        "rows": 100,
        "columns": 5,
        "headers": ["Nome", "Valor", "Data", "Status", "Observações"]
      }
    ],
    "total_sheets": 1
  }
}
```

## 🎯 **Embeddings**

### **Gerar Embeddings**
```http
POST /api/embeddings/generate
Content-Type: application/json
```

**Parâmetros:**
```json
{
  "document_id": 1,
  "async": true,
  "_token": "csrf_token"
}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Embeddings sendo gerados em background",
  "job_id": "embed_123456",
  "estimated_time": "2-5 minutos"
}
```

### **Status dos Embeddings**
```http
GET /api/embeddings/status/{document_id}
```

**Resposta:**
```json
{
  "success": true,
  "status": "completed",
  "progress": 100,
  "chunks_processed": 15,
  "total_chunks": 15,
  "processing_time": 45.2
}
```

### **Informações do Arquivo**
```http
GET /api/embeddings/file-info?filename=documento.pdf
```

**Resposta:**
```json
{
  "success": true,
  "file_info": {
    "filename": "documento.pdf",
    "extension": "pdf",
    "supports_embeddings": true,
    "estimated_chunks": 15,
    "estimated_processing_time": "2-5 minutos"
  }
}
```

## 📈 **Feedback e Analytics**

### **Enviar Feedback**
```http
POST /api/rag/feedback
Content-Type: application/json
```

**Parâmetros:**
```json
{
  "query": "Pergunta original",
  "document_id": 1,
  "rating": 1,
  "comment": "Resposta foi útil",
  "_token": "csrf_token"
}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Feedback registrado com sucesso"
}
```

### **Estatísticas de Feedback**
```http
GET /api/rag/feedback/stats
```

**Resposta:**
```json
{
  "ok": true,
  "stats": {
    "total_feedbacks": 150,
    "positive_ratio": 0.85,
    "daily_trend": [
      {"date": "2025-10-17", "positive": 12, "negative": 2}
    ],
    "top_queries": [
      {"query": "Sobre o que trata?", "count": 25}
    ]
  }
}
```

### **Feedbacks Recentes**
```http
GET /api/rag/feedback/recent
```

**Resposta:**
```json
{
  "ok": true,
  "feedbacks": [
    {
      "id": 1,
      "query": "Pergunta exemplo",
      "rating": 1,
      "comment": "Muito útil",
      "created_at": "2025-10-17T10:00:00Z"
    }
  ]
}
```

## 🔑 **Gerenciamento de API Keys**

### **Obter API Key**
```http
GET /api/user/api-key
```

**Resposta:**
```json
{
  "success": true,
  "api_key": "rag_1234567890abcdef...",
  "created_at": "2025-10-17T10:00:00Z",
  "last_used_at": "2025-10-17T15:30:00Z"
}
```

### **Gerar Nova API Key**
```http
POST /api/user/api-key/generate
```

**Resposta:**
```json
{
  "success": true,
  "api_key": "rag_new1234567890abcdef...",
  "message": "Nova API key gerada com sucesso"
}
```

### **Regenerar API Key**
```http
POST /api/user/api-key/regenerate
```

**Resposta:**
```json
{
  "success": true,
  "api_key": "rag_regenerated1234567890abcdef...",
  "message": "API key regenerada com sucesso"
}
```

### **Revogar API Key**
```http
DELETE /api/user/api-key/revoke
```

**Resposta:**
```json
{
  "success": true,
  "message": "API key revogada com sucesso"
}
```

## 📊 **Métricas e Monitoramento**

### **Métricas Gerais**
```http
GET /api/rag/metrics
```

**Resposta:**
```json
{
  "success": true,
  "metrics": {
    "total_documents": 150,
    "total_chunks": 5000,
    "total_users": 25,
    "total_queries": 1000,
    "average_response_time": 1.2,
    "cache_hit_rate": 0.18
  }
}
```

### **Estatísticas de Cache**
```http
GET /api/rag/cache/stats
```

**Resposta:**
```json
{
  "success": true,
  "cache_stats": {
    "total_queries": 1000,
    "cache_hits": 180,
    "hit_rate": 0.18,
    "memory_usage": "45.2 MB",
    "key_count": 150
  }
}
```

### **Limpar Cache**
```http
POST /api/rag/cache/clear
```

**Resposta:**
```json
{
  "success": true,
  "message": "Cache limpo com sucesso",
  "keys_removed": 150
}
```

### **Estatísticas de Embeddings**
```http
GET /api/rag/embeddings/stats
```

**Resposta:**
```json
{
  "success": true,
  "embeddings_stats": {
    "total_documents": 150,
    "documents_with_embeddings": 120,
    "total_chunks": 5000,
    "chunks_with_embeddings": 4800,
    "coverage_percentage": 96.0
  }
}
```

## 🏥 **Health Check**

### **Health Check Geral**
```http
GET /api/health
```

**Resposta:**
```json
{
  "status": "ok",
  "timestamp": "2025-10-17T10:00:00Z",
  "version": "1.0.0",
  "services": {
    "database": "ok",
    "redis": "ok",
    "python": "ok"
  }
}
```

### **Health Check Python**
```http
GET /api/rag/python-health
```

**Resposta:**
```json
{
  "success": true,
  "python_version": "Python 3.12.0",
  "script_exists": true,
  "dependencies_test": true,
  "database_stats": {
    "total_documents": 150,
    "total_chunks": 5000,
    "chunks_with_embeddings": 4800,
    "embedding_coverage": 96.0
  }
}
```

## ⚠️ **Códigos de Erro**

### **Códigos HTTP**
- `200` - Sucesso
- `201` - Criado com sucesso
- `400` - Requisição inválida
- `401` - Não autenticado
- `403` - Sem permissão
- `404` - Recurso não encontrado
- `422` - Dados de validação inválidos
- `429` - Limite de rate excedido
- `500` - Erro interno do servidor

### **Estrutura de Erro**
```json
{
  "success": false,
  "error": "Mensagem de erro",
  "code": "ERROR_CODE",
  "details": {
    "field": "Campo específico com erro"
  }
}
```

## 📝 **Exemplos de Uso**

### **JavaScript (Frontend)**
```javascript
// Upload de arquivo
const formData = new FormData();
formData.append('files[]', file);
formData.append('_token', csrfToken);

const response = await fetch('/api/rag/ingest', {
  method: 'POST',
  body: formData,
  credentials: 'same-origin'
});

const result = await response.json();
```

### **Python (API Integration)**
```python
import requests

# Configurar autenticação
headers = {
    'Authorization': 'Bearer rag_your_api_key_here',
    'Content-Type': 'application/json'
}

# Fazer busca RAG
data = {
    'query': 'Sobre o que trata este documento?',
    'document_id': 1,
    'top_k': 5
}

response = requests.post(
    'https://your-domain.com/api/rag/python-search',
    json=data,
    headers=headers
)

result = response.json()
```

### **cURL**
```bash
# Upload de arquivo
curl -X POST https://your-domain.com/api/rag/ingest \
  -H "Authorization: Bearer rag_your_api_key_here" \
  -F "files[]=@document.pdf"

# Busca RAG
curl -X POST https://your-domain.com/api/rag/python-search \
  -H "Authorization: Bearer rag_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Sobre o que trata este documento?",
    "document_id": 1,
    "top_k": 5
  }'
```

## 🔄 **Rate Limiting**

- **Upload**: 10 requisições por minuto
- **Busca RAG**: 60 requisições por minuto
- **API Keys**: 1000 requisições por dia
- **Feedback**: 100 requisições por hora

## 📋 **Limites de Arquivo**

- **Tamanho máximo**: 500MB por arquivo
- **Páginas máximas**: 5.000 páginas (OCR)
- **Vídeos**: 60 minutos máximo
- **Upload simultâneo**: 5 arquivos por vez

---

**Documentação da API atualizada em 2025-10-17** 📚
