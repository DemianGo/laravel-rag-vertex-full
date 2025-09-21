// Sistema RAG - Frontend JavaScript
let lastDocumentId = null;
let apiUrl = '';
let tenant = 'default';

// Configuração
function loadConfig() {
  apiUrl = localStorage.getItem('rag_api_url') || '';
  tenant = localStorage.getItem('rag_tenant') || 'default';
  document.getElementById('configApiUrl').value = apiUrl;
  document.getElementById('configTenant').value = tenant;
}

function saveConfig() {
  apiUrl = document.getElementById('configApiUrl').value.trim();
  tenant = document.getElementById('configTenant').value.trim() || 'default';
  localStorage.setItem('rag_api_url', apiUrl);
  localStorage.setItem('rag_tenant', tenant);
}

// Utilitários
function buildUrl(path) {
  const base = apiUrl.replace(/\/$/, '');
  return base ? base + path : path;
}

function log(message, type = 'info') {
  const console = document.getElementById('consoleLog');
  const item = document.createElement('div');
  item.className = 'fc-item';
  item.innerHTML = `<small class="${type === 'error' ? 'err' : 'ok'}">[${new Date().toLocaleTimeString()}] ${message}</small>`;
  console.appendChild(item);
  console.scrollTop = console.scrollHeight;
  
  // Mostra console se há erro
  if (type === 'error') {
    document.getElementById('debugConsole').classList.remove('d-none');
  }
}

async function apiRequest(url, options = {}) {
  try {
    log(`${options.method || 'GET'} ${url}`);
    const response = await fetch(buildUrl(url), {
      headers: {
        'Accept': 'application/json',
        ...options.headers
      },
      ...options
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${data.message || 'Erro na API'}`);
    }
    
    log(`Sucesso: ${response.status}`, 'success');
    return data;
  } catch (error) {
    log(`Erro: ${error.message}`, 'error');
    throw error;
  }
}

// Upload de Documentos
async function uploadDocument() {
  const titleEl = document.getElementById('docTitle');
  const fileEl = document.getElementById('docFile');
  const textEl = document.getElementById('docText');
  const resultEl = document.getElementById('uploadResult');
  const progressEl = document.getElementById('uploadProgress');
  const progressBar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  
  const title = titleEl.value.trim();
  const file = fileEl.files[0];
  const text = textEl.value.trim();
  
  if (!title) {
    alert('Digite um título para o documento');
    return;
  }
  
  if (!file && !text) {
    alert('Selecione um arquivo ou cole um texto');
    return;
  }
  
  try {
    resultEl.textContent = 'Carregando documento...';
    progressEl.classList.remove('d-none');
    progressBar.style.width = '0%';
    
    let response;
    
    if (file) {
      // Upload de arquivo
      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', title);
      formData.append('tenant', tenant);
      
      const xhr = new XMLHttpRequest();
      
      return new Promise((resolve, reject) => {
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const percent = Math.round((e.loaded * 100) / e.total);
            progressBar.style.width = percent + '%';
            progressText.textContent = `${percent}% - Enviando arquivo...`;
          }
        };
        
        xhr.onload = () => {
          progressEl.classList.add('d-none');
          if (xhr.status === 200) {
            const data = JSON.parse(xhr.responseText);
            resultEl.textContent = JSON.stringify(data, null, 2);
            if (data.document_id) {
              lastDocumentId = data.document_id;
              log(`Documento carregado: ID ${data.document_id}`, 'success');
            }
            titleEl.value = '';
            fileEl.value = '';
            updateRecentDocs();
          } else {
            const error = JSON.parse(xhr.responseText);
            resultEl.textContent = `Erro: ${error.message || 'Falha no upload'}`;
            log(`Erro no upload: ${xhr.status}`, 'error');
          }
        };
        
        xhr.onerror = () => {
          progressEl.classList.add('d-none');
          resultEl.textContent = 'Erro de conexão';
          log('Erro de conexão no upload', 'error');
        };
        
        xhr.open('POST', buildUrl('/rag/ingest'));
        xhr.send(formData);
      });
    } else {
      // Upload de texto
      progressText.textContent = 'Processando texto...';
      progressBar.style.width = '50%';
      
      response = await apiRequest('/rag/ingest', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          title: title,
          text: text,
          tenant: tenant
        })
      });
      
      progressBar.style.width = '100%';
      progressText.textContent = 'Concluído!';
      
      resultEl.textContent = JSON.stringify(response, null, 2);
      
      if (response.document_id) {
        lastDocumentId = response.document_id;
        log(`Documento criado: ID ${response.document_id}`, 'success');
      }
      
      titleEl.value = '';
      textEl.value = '';
      updateRecentDocs();
    }
  } catch (error) {
    resultEl.textContent = `Erro: ${error.message}`;
    log(`Erro no upload: ${error.message}`, 'error');
  } finally {
    setTimeout(() => {
      progressEl.classList.add('d-none');
    }, 2000);
  }
}

// Chat com IA
async function sendQuery() {
  const docIdEl = document.getElementById('chatDocId');
  const queryEl = document.getElementById('chatQuery');
  const modeEl = document.getElementById('chatMode');
  const formatEl = document.getElementById('chatFormat');
  const topKEl = document.getElementById('chatTopK');
  const resultEl = document.getElementById('chatResult');
  
  const query = queryEl.value.trim();
  const documentId = parseInt(docIdEl.value) || lastDocumentId;
  const mode = modeEl.value;
  const format = formatEl.value;
  const topK = parseInt(topKEl.value) || 5;
  
  if (!query) {
    alert('Digite uma pergunta');
    return;
  }
  
  if (!documentId) {
    alert('Carregue um documento primeiro ou informe o ID');
    return;
  }
  
  try {
    resultEl.textContent = 'Processando pergunta...';
    
    const response = await apiRequest('/rag/answer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        query: query,
        document_id: documentId,
        mode: mode,
        format: format,
        top_k: topK,
        tenant: tenant
      })
    });
    
    if (response.ok && response.answer) {
      resultEl.innerHTML = `
        <div class="mb-3">
          <strong>Pergunta:</strong> ${query}
        </div>
        <div class="mb-3">
          <strong>Resposta (${response.mode_used}):</strong><br>
          <div style="background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; margin-top: 0.5rem;">
            ${format === 'html' ? response.answer : response.answer}
          </div>
        </div>
        <div class="small text-muted">
          Documento: ${documentId} | Chunks: ${response.used_chunks} | Modo: ${response.mode_used}
        </div>
      `;
      log(`Query processada: ${response.mode_used}`, 'success');
    } else {
      resultEl.textContent = `Erro: ${response.error || 'Resposta inválida'}`;
    }
    
  } catch (error) {
    resultEl.textContent = `Erro: ${error.message}`;
    log(`Erro na query: ${error.message}`, 'error');
  }
}

// Atualizar documentos recentes
async function updateRecentDocs() {
  const docsEl = document.getElementById('recentDocs');
  const docsListEl = document.getElementById('docsList');
  
  try {
    // Como não temos endpoint específico para listar docs, 
    // simulamos com informação local
    if (lastDocumentId) {
      docsEl.innerHTML = `
        <div class="mb-2 p-2 border rounded">
          <strong>Último documento</strong><br>
          <small>ID: ${lastDocumentId}</small><br>
          <button class="btn btn-sm btn-outline-primary mt-1" onclick="document.getElementById('chatDocId').value=${lastDocumentId}">
            Usar este documento
          </button>
        </div>
      `;
      
      docsListEl.innerHTML = `
        <div class="mb-2 p-3 border rounded">
          <div class="d-flex justify-content-between">
            <strong>Documento ${lastDocumentId}</strong>
            <span class="badge bg-success">Ativo</span>
          </div>
          <small class="text-muted">Último documento carregado</small>
        </div>
      `;
    }
  } catch (error) {
    log(`Erro ao atualizar docs: ${error.message}`, 'error');
  }
}

// Teste de conectividade
async function testConnection() {
  try {
    log('Testando conexão...');
    
    // Tenta fazer uma requisição simples para testar
    const response = await fetch(buildUrl('/rag/answer'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        query: 'teste',
        document_id: 1,
        mode: 'direct'
      })
    });
    
    if (response.status === 200 || response.status === 422) {
      // 422 é OK - significa que o endpoint existe mas faltam dados
      log('Conexão OK - API funcionando', 'success');
      alert('Conexão com a API funcionando!');
    } else {
      log(`Conexão falhou: HTTP ${response.status}`, 'error');
      alert(`Problema na conexão: HTTP ${response.status}`);
    }
  } catch (error) {
    log(`Erro de conexão: ${error.message}`, 'error');
    alert(`Erro de conexão: ${error.message}`);
  }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
  loadConfig();
  
  // Upload
  document.getElementById('btnUpload').addEventListener('click', uploadDocument);
  
  // Chat
  document.getElementById('btnChat').addEventListener('click', sendQuery);
  
  // Configuração
  document.getElementById('btnConfig').addEventListener('click', function() {
    new bootstrap.Modal(document.getElementById('configModal')).show();
  });
  
  document.getElementById('btnSaveConfig').addEventListener('click', function() {
    saveConfig();
    bootstrap.Modal.getInstance(document.getElementById('configModal')).hide();
    log('Configuração salva', 'success');
  });
  
  // Outros botões
  document.getElementById('btnHealth').addEventListener('click', testConnection);
  document.getElementById('btnRefreshDocs').addEventListener('click', updateRecentDocs);
  document.getElementById('btnCloseConsole').addEventListener('click', function() {
    document.getElementById('debugConsole').classList.add('d-none');
  });
  
  // Enter para enviar query
  document.getElementById('chatQuery').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && e.ctrlKey) {
      sendQuery();
    }
  });
  
  log('Frontend RAG inicializado', 'success');
});