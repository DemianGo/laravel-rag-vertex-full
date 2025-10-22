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
  
  // PYTHON RAG - Event Listeners
  document.getElementById('pythonSearchBtn').addEventListener('click', async function() {
    const query = document.getElementById('pythonQuery').value.trim();
    if (!query) {
      document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-warning">Digite uma query</div>';
      return;
    }

    const topK = parseInt(document.getElementById('pythonTopK').value) || 5;
    const threshold = parseFloat(document.getElementById('pythonThreshold').value) || 0.3;
    const docId = document.getElementById('pythonDocId').value ? parseInt(document.getElementById('pythonDocId').value) : null;
    const includeAnswer = document.getElementById('pythonIncludeAnswer').checked;
    const strictness = parseInt(document.getElementById('pythonStrictness').value) || 2;
    const mode = document.getElementById('pythonMode').value || "auto";
    const format = document.getElementById('pythonFormat').value || "plain";
    const length = document.getElementById('pythonLength').value || "auto";
    const citations = parseInt(document.getElementById('pythonCitations').value) || 0;

    document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-info">🔍 Buscando com Python RAG...</div>';
    document.getElementById('pythonChunks').innerHTML = '';
    document.getElementById('pythonAnswer').innerHTML = '';
    document.getElementById('pythonMetadata').textContent = '';

    try {
      const payload = {
        query: query,
        top_k: topK,
        threshold: threshold,
        include_answer: includeAnswer,
        strictness: strictness,
        mode: mode,
        format: format,
        length: length,
        citations: citations
      };
      
      if (docId) payload.document_id = docId;

      const data = await apiRequest('/api/rag/python-search', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      
      if (data.ok || data.success) {
        document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-success">✅ Busca concluída com sucesso!</div>';
        
        // Exibir chunks
        let chunksHtml = '';
        if (data.chunks && data.chunks.length > 0) {
          chunksHtml = data.chunks.map(chunk => 
            `<div class="border-bottom pb-2 mb-2">
              <strong>Chunk ID:</strong> ${chunk.id} | 
              <strong>Doc:</strong> ${chunk.document_id} | 
              <strong>Similarity:</strong> ${chunk.similarity ? chunk.similarity.toFixed(4) : 'N/A'}
              <br><small class="text-muted">${chunk.content.substring(0, 200)}${chunk.content.length > 200 ? '...' : ''}</small>
            </div>`
          ).join('');
        } else {
          chunksHtml = `<em>Nenhum chunk encontrado (${data.used_chunks ? data.used_chunks.length : 0} chunks usados)</em>`;
        }
        document.getElementById('pythonChunks').innerHTML = chunksHtml;
        
        // Exibir resposta
        document.getElementById('pythonAnswer').innerHTML = data.answer ? 
          `<div class="text-wrap">${data.answer.replace(/\n/g, '<br>')}</div>` : 
          '<em>Resposta não gerada</em>';
        
        // Exibir metadados
        const metadata = data.debug || data.metadata || {};
        const displayData = {
          mode_used: data.mode_used,
          format: data.format,
          sources: data.sources,
          used_chunks: data.used_chunks,
          debug: metadata
        };
        document.getElementById('pythonMetadata').textContent = JSON.stringify(displayData, null, 2);
        
      } else {
        document.getElementById('pythonStatus').innerHTML = `<div class="alert alert-danger">❌ Erro: ${data.error}</div>`;
      }
      
    } catch(e) {
      document.getElementById('pythonStatus').innerHTML = `<div class="alert alert-danger">❌ Erro: ${e.message}</div>`;
      log(`Erro Python RAG: ${e.message}`, 'error');
    }
  });

  document.getElementById('pythonCompareBtn').addEventListener('click', async function() {
    const query = document.getElementById('pythonQuery').value.trim();
    if (!query) {
      document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-warning">Digite uma query</div>';
      return;
    }

    const docId = document.getElementById('pythonDocId').value ? parseInt(document.getElementById('pythonDocId').value) : null;
    if (!docId) {
      document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-warning">Document ID é obrigatório para comparação</div>';
      return;
    }

    document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-info">⚖️ Comparando PHP vs Python...</div>';

    try {
      const data = await apiRequest('/api/rag/compare-search', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          query: query,
          document_id: docId,
          top_k: 5
        })
      });

      if (data.success) {
        const comparison = data.comparison;
        document.getElementById('pythonStatus').innerHTML = `
          <div class="alert alert-success">
            <strong>Comparação Concluída!</strong><br>
            <strong>PHP:</strong> ${comparison.php.chunks_found} chunks em ${comparison.php.execution_time}ms<br>
            <strong>Python:</strong> ${comparison.python.chunks_found} chunks em ${comparison.python.execution_time}ms<br>
            <strong>Vencedor:</strong> ${data.winner.speed} (velocidade), ${data.winner.chunks} (chunks)
          </div>
        `;
      } else {
        document.getElementById('pythonStatus').innerHTML = `<div class="alert alert-danger">❌ Erro na comparação: ${data.error}</div>`;
      }
    } catch(e) {
      document.getElementById('pythonStatus').innerHTML = `<div class="alert alert-danger">❌ Erro: ${e.message}</div>`;
      log(`Erro comparação: ${e.message}`, 'error');
    }
  });

  document.getElementById('pythonHealthBtn').addEventListener('click', async function() {
    document.getElementById('pythonStatus').innerHTML = '<div class="alert alert-info">🏥 Verificando saúde do Python...</div>';

    try {
      const data = await apiRequest('/api/rag/python-health', {
        method: 'GET'
      });

      if (data.success) {
        document.getElementById('pythonStatus').innerHTML = `
          <div class="alert alert-success">
            <strong>Python RAG Saudável!</strong><br>
            <small>Versão: ${data.python_version}<br>
            Documentos: ${data.database_stats.total_documents}<br>
            Chunks: ${data.database_stats.total_chunks} (${data.database_stats.embedding_coverage}% com embeddings)</small>
          </div>
        `;
      } else {
        document.getElementById('pythonStatus').innerHTML = `<div class="alert alert-danger">❌ Python RAG não está saudável</div>`;
      }
    } catch(e) {
      document.getElementById('pythonStatus').innerHTML = `<div class="alert alert-danger">❌ Erro: ${e.message}</div>`;
      log(`Erro health check: ${e.message}`, 'error');
    }
  });
  
  // === FUNCIONALIDADE DE VÍDEO ===
  let currentVideoJobId = null;
  let videoStatusInterval = null;

  // Processar vídeo
  document.getElementById('processVideoBtn').addEventListener('click', async () => {
    const videoUrl = document.getElementById('videoUrl').value.trim();
    
    if (!videoUrl) {
      alert('Por favor, insira a URL do vídeo YouTube');
      return;
    }
    
    try {
      document.getElementById('processVideoBtn').disabled = true;
      document.getElementById('processVideoBtn').textContent = '⏳ Processando...';
      
      // Use Python video server directly
      const response = await fetch('http://localhost:8001/video/process', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          url: videoUrl,
          tenant_slug: window.userTenant || 'user_1'
        })
      });
      
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.error || 'Video processing failed');
      }
      
      if (data.success) {
        currentVideoJobId = data.document_id;
        
        document.getElementById('videoStatus').innerHTML = `
          <div class="alert alert-success">
            ✅ Vídeo processado com sucesso!<br>
            <small>Documento ID: ${data.document_id} | Transcrição: ${data.transcript_length} caracteres</small>
          </div>
        `;
        
        document.getElementById('videoStatusBtn').style.display = 'inline-block';
        document.getElementById('videoProgress').style.display = 'block';
        
        // Show transcription modal with real transcription
        document.getElementById('videoTitle').textContent = `YouTube Video: ${data.video_id}`;
        document.getElementById('videoInfo').textContent = `Documento ID: ${data.document_id} | Transcrição: ${data.transcript_length} caracteres`;
        document.getElementById('videoTranscription').textContent = data.transcript || 'Transcrição não disponível';
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('videoTranscriptionModal'));
        modal.show();
        
      } else {
        const error = await response.json();
        document.getElementById('videoStatus').innerHTML = `
          <div class="alert alert-danger">
            ❌ Erro: ${error.message || 'Erro desconhecido'}
          </div>
        `;
      }
    } catch (e) {
      document.getElementById('videoStatus').innerHTML = `
        <div class="alert alert-danger">
          ❌ Erro: ${e.message}
        </div>
      `;
      log(`Erro processamento vídeo: ${e.message}`, 'error');
    } finally {
      document.getElementById('processVideoBtn').disabled = false;
      document.getElementById('processVideoBtn').textContent = '🎬 Processar Vídeo';
    }
  });

  // Verificar status do vídeo
  document.getElementById('videoStatusBtn').addEventListener('click', async () => {
    if (currentVideoJobId) {
      await checkVideoStatus();
    }
  });

  function startVideoStatusPolling() {
    if (videoStatusInterval) {
      clearInterval(videoStatusInterval);
    }
    
    videoStatusInterval = setInterval(async () => {
      await checkVideoStatus();
    }, 3000); // Poll a cada 3 segundos
  }

  async function checkVideoStatus() {
    if (!currentVideoJobId) return;
    
    try {
      const response = await apiRequest(`/api/video/status/${currentVideoJobId}`);
      
      if (response.ok) {
        const data = await response.json();
        
        if (data.status === 'completed') {
          // Parar polling
          if (videoStatusInterval) {
            clearInterval(videoStatusInterval);
            videoStatusInterval = null;
          }
          
          // Mostrar resultado
          showVideoResult(data);
          
        } else if (data.status === 'failed') {
          // Parar polling
          if (videoStatusInterval) {
            clearInterval(videoStatusInterval);
            videoStatusInterval = null;
          }
          
          document.getElementById('videoStatus').innerHTML = `
            <div class="alert alert-danger">
              ❌ Processamento falhou: ${data.error_message || 'Erro desconhecido'}
            </div>
          `;
          
        } else if (data.status === 'processing') {
          // Atualizar progresso
          const progress = data.progress || {};
          const percentage = progress.percentage || 0;
          
          document.getElementById('videoStatus').innerHTML = `
            <div class="alert alert-warning">
              ⏳ Processando...<br>
              <small>Etapa: ${progress.current_step || 'processando'}</small>
            </div>
          `;
          
          const progressBar = document.querySelector('#videoProgress .progress-bar');
          progressBar.style.width = `${percentage}%`;
          progressBar.textContent = `${percentage}%`;
        }
      }
    } catch (e) {
      log(`Erro verificação status: ${e.message}`, 'error');
    }
  }

  function showVideoResult(data) {
    document.getElementById('videoStatus').innerHTML = `
      <div class="alert alert-success">
        ✅ Processamento concluído!<br>
        <small>Concluído em: ${data.completed_at}</small>
      </div>
    `;
    
    // Mostrar modal com transcrição
    document.getElementById('videoTitle').textContent = data.video_info.title || 'Vídeo Processado';
    document.getElementById('videoInfo').textContent = `
      Duração: ${data.video_info.duration_seconds}s | Canal: ${data.video_info.channel || 'N/A'}
    `;
    
    // Carregar transcrição (simulada por enquanto)
    document.getElementById('videoTranscription').innerHTML = `
      <p class="text-muted">Transcrição disponível para download</p>
      <p><strong>RAG Document ID:</strong> ${data.rag_document_id || 'N/A'}</p>
    `;
    
    // Botões de download
    const downloadsDiv = document.getElementById('videoDownloads');
    downloadsDiv.innerHTML = '';
    
    if (data.downloads) {
      if (data.downloads.audio_url) {
        downloadsDiv.innerHTML += `
          <a href="${data.downloads.audio_url}" class="btn btn-outline-primary btn-sm" target="_blank">
            🎵 Download Áudio
          </a>
        `;
      }
      
      if (data.downloads.transcription_url) {
        downloadsDiv.innerHTML += `
          <a href="${data.downloads.transcription_url}" class="btn btn-outline-secondary btn-sm" target="_blank">
            📄 Download Transcrição
          </a>
        `;
      }
    }
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('videoTranscriptionModal'));
    modal.show();
  }

  // Usar no RAG
  document.getElementById('btnUseInRAG').addEventListener('click', () => {
    // Fechar modal e focar na query
    const modal = bootstrap.Modal.getInstance(document.getElementById('videoTranscriptionModal'));
    modal.hide();
    
    // Ativar aba de busca e preencher query
    const pythonQuery = document.getElementById('pythonQuery');
    pythonQuery.value = 'Conteúdo do vídeo processado';
    pythonQuery.focus();
    
    log('Vídeo integrado ao RAG - pronto para consultas!', 'success');
  });

  log('Frontend RAG inicializado', 'success');
});