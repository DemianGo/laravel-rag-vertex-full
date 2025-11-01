# Checklist de Deploy Nginx - PASSO A PASSO

## ✅ Arquivos Criados
- [x] `scripts/docker/nginx.conf` - Configuração Docker Compose
- [x] `scripts/docker/nginx.conf.local` - Configuração local/dev
- [x] `DEPLOYMENT_NGINX.md` - Documentação completa
- [x] Este checklist

## ⏳ Próximos Passos

### 1️⃣ ESCOLHER MODO DE DEPLOY

**Opção A: Docker Compose (Recomendado)**
```bash
cd scripts/docker
docker-compose --profile production up -d
```
✅ Fácil
✅ Isolado
✅ Pronto para produção
❌ Precisa Docker

**Opção B: Nginx Local**
```bash
sudo apt install nginx
sudo cp scripts/docker/nginx.conf.local /etc/nginx/nginx.conf
sudo nginx -t
sudo systemctl start nginx
```
✅ Sem Docker
✅ Rápido
❌ Precisa configurar sistema
❌ Menos isolado

**Opção C: FastAPI StaticFiles (Só dev)**
```python
# Adicionar 2 linhas em scripts/api/main.py
from fastapi.staticfiles import StaticFiles
app.mount("/", StaticFiles(directory="public", html=True), name="static")
```
✅ Zero configuração
❌ Não escala para produção
❌ Performance inferior

### 2️⃣ AJUSTAR DOCKER-COMPOSE.YML

**Verificar linhas 75-89:**
```yaml
nginx:
  image: nginx:alpine
  container_name: extraction-api-nginx
  restart: unless-stopped
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - ./nginx.conf:/etc/nginx/nginx.conf:ro  # ← VERIFICAR CAMINHO
    - nginx_logs:/var/log/nginx
  depends_on:
    - api
  profiles:
    - production
```

**⚠️ VERIFICAR:**
- Caminho `./nginx.conf` está correto?
- Porta 8001 vs 8002? (Docker usa 8001, local usa 8002)

### 3️⃣ TESTAR

**Testar configuração Nginx:**
```bash
docker-compose --profile production config
```

**Subir containers:**
```bash
docker-compose --profile production up -d
```

**Ver logs:**
```bash
docker-compose --profile production logs -f
```

**Testar endpoints:**
```bash
curl http://localhost/
curl http://localhost/auth/register
curl http://localhost/api/rag/python-health
curl http://localhost/health
```

### 4️⃣ FRONTENDS

**Verificar HTML estático:**
- [ ] http://localhost/ → Welcome page
- [ ] http://localhost/auth/login.html → Login
- [ ] http://localhost/auth/register.html → Register
- [ ] http://localhost/rag-frontend/index.html → RAG Console
- [ ] http://localhost/admin/login.html → Admin

### 5️⃣ BACKEND

**Verificar APIs:**
- [ ] POST /auth/register → Criar usuário
- [ ] POST /auth/login → Login
- [ ] GET /v1/user/info → Info usuário
- [ ] POST /api/rag/python-search → Busca RAG
- [ ] Upload documento → Funciona?

### 6️⃣ PARAR LARAVEL

**Após validar tudo:**
```bash
pkill -f "php artisan serve"
```

### 7️⃣ MONITORAMENTO

**Ver stats:**
```bash
docker stats
```

**Ver conexões:**
```bash
netstat -tuln | grep ":80"
```

---

## 🎯 DECISÃO NECESSÁRIA

**Qual modo você quer usar AGORA?**

1. Docker Compose → Vamos ajustar docker-compose.yml
2. Nginx Local → Vamos instalar e configurar
3. FastAPI StaticFiles → Adicionar 2 linhas (só dev)

**Responda com o número!**

---
