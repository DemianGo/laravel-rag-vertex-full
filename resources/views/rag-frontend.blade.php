<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>RAG Console — PRO (Light)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
  <!-- File Validator (independent module) -->
  <script src="{{ asset('rag-frontend/file-validator.js') }}"></script>
  <style>
    :root {
      --console-bg: #f8f9fa;
      --console-border: #dee2e6;
      --console-text: #212529;
      --zone-border: #cbd5e1;
      --zone-bg: #ffffff;
      --zone-hover: #eef2ff;
    }
    body { background:#fff; color:#212529; padding-bottom: 300px; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
    pre { white-space: pre-wrap; word-break: break-word; }
    .card { border-radius: 1rem; }
    .btn { border-radius: .75rem; }
    /* Debug console (fixo, claro) */
    .fixed-console {
      position: fixed; left:0; right:0; bottom:0; z-index:1030;
      background: var(--console-bg); border-top: 1px solid var(--console-border); color: var(--console-text);
      max-height: 260px; overflow: hidden;
      box-shadow: 0 -6px 30px rgba(0,0,0,.06);
    }
    .console-body { max-height: 190px; overflow: auto; }
    .badge-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .pointer { cursor: pointer; }
    /* Upload zone (drag & drop) */
    .dropzone {
      border: 2px dashed var(--zone-border);
      background: var(--zone-bg);
      border-radius: 14px;
      padding: 20px;
      transition: background .2s, border-color .2s;
      text-align: center;
    }
    .dropzone.dragover { background: var(--zone-hover); border-color: #94a3b8; }
    .dz-icon { font-size: 32px; line-height: 1; }
    .dz-sub { color:#6c757d; }
    .file-chip {
      border: 1px solid #e9ecef; border-radius: 999px; padding: 4px 10px; display: inline-flex; gap:8px; align-items:center; margin: 4px 6px 0 0;
      background: #f8f9fa; font-size: 13px; line-height: 1;
    }
    .file-chip .badge { font-size: 11px; padding: 2px 6px; }
    .file-chip .remove { cursor: pointer; color: #dc3545; font-weight: bold; }
    /* Suggested questions */
    .suggested-question {
      cursor: pointer;
      transition: background-color 0.2s, border-color 0.2s;
    }
    .suggested-question:hover {
      background-color: #e7f3ff;
      border-color: #0d6efd;
    }
    /* User info header */
    .user-info-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .user-info-header .user-name {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 0.25rem;
    }
    .user-info-header .user-email {
      font-size: 0.875rem;
      opacity: 0.9;
    }
    .user-info-header .logout-btn {
      background: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      text-decoration: none;
      transition: all 0.2s;
    }
    .user-info-header .logout-btn:hover {
      background: rgba(255,255,255,0.3);
      border-color: rgba(255,255,255,0.5);
    }
  </style>
</head>
<body>

<!-- User Info Header -->
<div class="container mt-4">
  <div class="user-info-header d-flex justify-content-between align-items-center">
    <div>
      <div class="user-name">👋 Olá, {{ Auth::user()->name }}</div>
      <div class="user-email">{{ Auth::user()->email }}</div>
    </div>
    <div class="d-flex gap-3 align-items-center">
      <div class="text-end">
        <div class="small opacity-75">Plano</div>
        <div class="fw-bold">{{ ucfirst(Auth::user()->plan) }}</div>
      </div>
      <div class="text-end">
        <div class="small opacity-75">Tokens</div>
        <div class="fw-bold">{{ Auth::user()->tokens_used }}/{{ Auth::user()->tokens_limit }}</div>
      </div>
      <form method="POST" action="{{ route('logout') }}" class="mb-0">
        @csrf
        <button type="submit" class="logout-btn border-0">
          🚪 Sair
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Original RAG Console Content -->
<div id="rag-console-content">
  {!! file_get_contents(public_path('rag-frontend/index.html')) !!}
</div>

<!-- RAG Client JS -->
<script src="{{ asset('rag-frontend/rag-client.js') }}"></script>

<script>
  // Remove duplicate HTML structure (keep only body content)
  document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('rag-console-content');
    if (content) {
      // Extract only the body content from the loaded HTML
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = content.innerHTML;
      
      // Find the body content (everything inside <body>)
      const bodyContent = tempDiv.querySelector('body');
      if (bodyContent) {
        content.innerHTML = bodyContent.innerHTML;
      }
    }
  });
</script>

</body>
</html>

