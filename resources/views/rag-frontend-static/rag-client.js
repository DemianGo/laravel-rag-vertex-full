// public/front/rag-client.js
// Cliente RAG não-destrutivo: adiciona funções globais sem remover nada do seu front.
// Expõe window.RAG com ingest (file/text), query e answer, preservando o último document_id.

(() => {
  const BASE = window.location.origin;
  // rotas ponte sem /api (mantém compatibilidade com seu front)
  const URLS = {
    ingest:  BASE + '/rag/ingest',
    query:   BASE + '/rag/query',
    answer:  BASE + '/rag/answer',
    docs:    'http://localhost:8002/api/docs/list',  // FastAPI direto
    ping:    BASE + '/rag/ping',
  };

  const KEY_LAST_DOC = 'rag:lastDocumentId';

  async function requestJSON(url, { method = 'GET', headers = {}, body = undefined, form = false } = {}) {
    const h = new Headers(headers);
    // sempre pedimos JSON no retorno
    h.set('Accept', 'application/json');

    let resp;
    try {
      resp = await fetch(url, {
        method,
        headers: form ? h : (h.set('Content-Type', 'application/json'), h),
        body: form ? body : (body ? JSON.stringify(body) : undefined),
        credentials: 'same-origin',
      });
    } catch (e) {
      return { ok: false, http: 0, error: 'Falha de rede: ' + e.message, raw: '' };
    }

    const text = await resp.text();
    let data = null;
    try { data = text ? JSON.parse(text) : null; } catch { /* não-JSON */ }

    if (!resp.ok) {
      return { ok: false, http: resp.status, error: (data && data.error) || text || 'Erro HTTP', raw: text };
    }
    if (data === null || typeof data !== 'object') {
      // servidor respondeu 200 mas não em JSON → devolver texto cru
      return { ok: false, http: resp.status, error: 'Resposta não-JSON', raw: text };
    }
    return data;
  }

  function setLastDocId(id) { try { localStorage.setItem(KEY_LAST_DOC, String(id)); } catch {} }
  function getLastDocId()   { try { return parseInt(localStorage.getItem(KEY_LAST_DOC) || '0', 10) || null; } catch { return null; } }

  async function ingestFile(file, title = '') {
    const fd = new FormData();
    fd.append('file', file);
    if (title) fd.append('title', title);

    const res = await requestJSON(URLS.ingest, { method: 'POST', form: true, body: fd });
    if (res && res.ok && res.document_id) {
      setLastDocId(res.document_id);
    }
    return res;
  }

  async function ingestText(title, text) {
    const res = await requestJSON(URLS.ingest, { method: 'POST', body: { title, text } });
    if (res && res.ok && res.document_id) {
      setLastDocId(res.document_id);
    }
    return res;
  }

  // normalize parâmetros que seu front já usa, sem forçar um nome específico
  function normQueryParams(params = {}) {
    const p = { ...params };
    // múltiplos nomes aceitos no backend; aqui fazemos um merge seguro
    const q = p.q || p.query || p.question || p.prompt || p.text || p.message || p.msg || p.qtext || p.search || '';
    let top_k = p.top_k ?? p.topk ?? p.k ?? p.top ?? p.limit ?? p.n;
    if (top_k == null) top_k = 5;

    let document_id = p.document_id ?? p.doc_id ?? p.id ?? null;
    if (!document_id) {
      const last = getLastDocId();
      if (last) document_id = last; // força o doc mais recente por padrão
    }
    const title = p.title || p.filename || p.name || '';

    return { q, top_k, document_id, title };
  }

  async function query(params = {}) {
    const { q, top_k, document_id, title } = normQueryParams(params);
    const body = { q, top_k };
    if (document_id) body.document_id = document_id;
    if (title) body.title = title;

    return await requestJSON(URLS.query, { method: 'POST', body });
  }

  async function answer(params = {}) {
    const { q, top_k, document_id, title } = normQueryParams(params);
    const body = { q, top_k };
    if (document_id) body.document_id = document_id;
    if (title) body.title = title;

    return await requestJSON(URLS.answer, { method: 'POST', body });
  }

  // util opcional p/ debug na UI
  async function listDocs() {
    return await requestJSON(URLS.docs, { method: 'GET' });
  }

  // PYTHON RAG FUNCTIONS
  async function pythonSearch(params) {
    const body = {
      query: params.query,
      top_k: params.top_k || 5,
      threshold: params.threshold || 0.3,
      include_answer: params.include_answer !== false,
      strictness: params.strictness || 2,
      mode: params.mode || 'auto',
      format: params.format || 'plain',
      length: params.length || 'auto',
      citations: params.citations || 0,
      use_smart_mode: params.use_smart_mode !== false,  // Padrão: true
      use_full_document: params.use_full_document || false
    };
    if (params.document_id) body.document_id = params.document_id;
    
    return await requestJSON(BASE + '/api/rag/python-search', { method: 'POST', body });
  }

  async function pythonHealth() {
    return await requestJSON(BASE + '/api/rag/python-health', { method: 'GET' });
  }

  async function compareSearch(params) {
    const body = {
      query: params.query,
      document_id: params.document_id,
      top_k: params.top_k || 5
    };
    
    return await requestJSON(BASE + '/api/rag/compare-search', { method: 'POST', body });
  }

  async function getSuggestedQuestions(documentId) {
    try {
      const res = await requestJSON(URLS.docs, { method: 'GET' });
      if (res && res.ok && res.docs) {
        const doc = res.docs.find(d => d.id === documentId);
        if (doc && doc.metadata) {
          try {
            const metadata = typeof doc.metadata === 'string' ? JSON.parse(doc.metadata) : doc.metadata;
            return metadata.suggested_questions || [];
          } catch {
            return [];
          }
        }
      }
    } catch (e) {
      console.error('Erro ao buscar perguntas sugeridas:', e);
    }
    return [];
  }

  async function submitFeedback(params) {
    const body = {
      query: params.query,
      document_id: params.document_id,
      rating: params.rating, // 1 (positive) ou -1 (negative)
      metadata: params.metadata || {}
    };
    
    return await requestJSON(BASE + '/api/rag/feedback', { method: 'POST', body });
  }

  async function getFeedbackStats() {
    return await requestJSON(BASE + '/api/rag/feedback/stats', { method: 'GET' });
  }

  // expõe sem colidir: se já existir window.RAG, apenas estende
  const api = { 
    ingestFile, ingestText, query, answer, listDocs, getLastDocId, setLastDocId, 
    pythonSearch, pythonHealth, compareSearch, getSuggestedQuestions,
    submitFeedback, getFeedbackStats,
    _urls: URLS 
  };
  if (window.RAG && typeof window.RAG === 'object') {
    Object.assign(window.RAG, api);
  } else {
    window.RAG = api;
  }

})();
