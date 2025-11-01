# Opções para Servir HTML Estático (SEM Laravel)

## ✅ OPÇÃO 1: FastAPI StaticFiles (Mais Simples)

**Adicionar 2 linhas ao `scripts/api/main.py`:**

```python
from fastapi.staticfiles import StaticFiles  # Adicionar import

# ... código existente ...

# Montar diretório público ANTES dos routers
app.mount("/", StaticFiles(directory="public", html=True), name="static")
```

**Vantagens:**
- ✅ Zero configuração adicional
- ✅ Tudo em um único servidor (porta 8002)
- ✅ Ideal para desenvolvimento
- ✅ FastAPI já está rodando

**Desvantagens:**
- ⚠️ Performance inferior ao nginx para arquivos estáticos
- ⚠️ FastAPI ocupado servindo HTML vs API

---

## ✅ OPÇÃO 2: Nginx (Recomendado para Produção)

**Instalar nginx:**
```bash
sudo apt install nginx
```

**Configurar `/etc/nginx/sites-available/rag-system`:**
```nginx
server {
    listen 8000;
    server_name localhost;
    
    root /var/www/html/laravel-rag-vertex-full/public;
    index index.html;
    
    # Servir arquivos estáticos
    location / {
        try_files $uri $uri/ =404;
    }
    
    # Proxy FastAPI
    location /api/ {
        proxy_pass http://127.0.0.1:8002;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    
    # Proxy /auth
    location /auth/ {
        proxy_pass http://127.0.0.1:8002;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    
    # Proxy /v1
    location /v1/ {
        proxy_pass http://127.0.0.1:8002;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Ativar:**
```bash
sudo ln -s /etc/nginx/sites-available/rag-system /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

**Vantagens:**
- ✅ Performance máxima para arquivos estáticos
- ✅ Separar static de API
- ✅ Pronto para produção
- ✅ HTTPS automático (Let's Encrypt)

**Desvantagens:**
- ⚠️ Instalação e configuração inicial
- ⚠️ Mais complexo

---

## 🎯 Recomendação

**Desenvolvimento**: Opção 1 (FastAPI StaticFiles)
**Produção**: Opção 2 (Nginx)

---
