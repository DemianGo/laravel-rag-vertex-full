# Deploy com Nginx - Guia Completo

## 🎯 Objetivo
Servir o sistema completo com Nginx + FastAPI para **milhares de usuários simultâneos**.

---

## 🐳 OPÇÃO 1: Docker Compose (Produção)

### 1. Arquivos necessários
```bash
scripts/docker/
├── docker-compose.yml  ✅ (já existe)
├── nginx.conf          ✅ (acabamos de criar)
├── Dockerfile          ✅ (já existe)
└── start_api.sh        ✅ (já existe)
```

### 2. Deploy
```bash
cd scripts/docker

# Build e subir tudo
docker-compose --profile production up -d

# Ver logs
docker-compose --profile production logs -f

# Parar
docker-compose --profile production down
```

### 3. Estrutura
```
┌─────────────────────────────────────────┐
│  Nginx (Porta 80)                       │
│  • / → static HTML                      │
│  • /auth → FastAPI                      │
│  • /api → FastAPI                       │
│  • /v1 → FastAPI                        │
└──────────────┬──────────────────────────┘
               │
               v
┌─────────────────────────────────────────┐
│  FastAPI (Porta 8001)                   │
│  • RAG Search                           │
│  • Document Upload                      │
│  • Auth                                 │
└──────────────┬──────────────────────────┘
               │
               v
┌─────────────────────────────────────────┐
│  Redis (Porta 6379)                     │
│  • Cache                                │
└─────────────────────────────────────────┘
```

### 4. Configurações importantes
- **Worker connections**: 4096 (milhares de usuários)
- **Client max body**: 500MB (uploads grandes)
- **Rate limiting**: 100 req/min API, 10 req/min auth
- **Upload rate limit**: 5 req/min
- **Timeouts**: 300s API, 600s upload
- **Gzip**: Habilitado
- **Connection pooling**: 32 keepalive

---

## 🐧 OPÇÃO 2: Nginx Local (Development/Test)

### 1. Instalar Nginx
```bash
sudo apt update
sudo apt install nginx -y
```

### 2. Copiar configuração
```bash
sudo cp scripts/docker/nginx.conf.local /etc/nginx/nginx.conf
```

### 3. Iniciar Nginx
```bash
sudo nginx -t  # Testar configuração
sudo systemctl start nginx
sudo systemctl enable nginx
```

### 4. Verificar
```bash
curl http://localhost:8000/
curl http://localhost:8000/health
```

---

## 📊 Performance Esperada

### Com Nginx (Docker Compose)
- **Workers**: 4-8 (auto detectado)
- **Worker connections**: 4096 cada
- **Total**: 16.000-32.000 conexões simultâneas
- **Throughput**: 10.000+ req/s
- **Memory**: ~200MB Nginx + ~500MB FastAPI + ~50MB Redis

### Sem Nginx (Só FastAPI)
- **Workers**: 1-2
- **Worker connections**: 2048 cada
- **Total**: 2.000-4.000 conexões simultâneas
- **Throughput**: 1.000+ req/s

---

## 🔍 Monitoramento

### Ver logs Nginx
```bash
# Docker
docker-compose --profile production logs -f nginx

# Local
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
```

### Ver conexões ativas
```bash
# Nginx
sudo nginx -s reload  # Recarregar sem parar

# Docker
docker-compose --profile production ps
docker stats
```

### Health checks
```bash
curl http://localhost/health        # FastAPI health
curl http://localhost/metrics       # FastAPI metrics
```

---

## 🚀 Comandos Úteis

### Parar Laravel
```bash
pkill -f "php artisan serve"
```

### Iniciar FastAPI
```bash
cd /var/www/html/laravel-rag-vertex-full
export PYTHONPATH=/var/www/html/laravel-rag-vertex-full/scripts
python3 scripts/api/main.py &
```

### Iniciar Redis
```bash
# Docker
docker-compose up -d redis

# Local
sudo systemctl start redis
```

### Verificar portas
```bash
netstat -tuln | grep -E ":80|:8000|:8001|:8002|:6379"
```

---

## ⚠️ Troubleshooting

### 502 Bad Gateway
- FastAPI não está rodando
- Porta errada no nginx.conf
- Solução: `docker-compose logs api`

### 413 Request Entity Too Large
- Arquivo > 500MB
- Solução: Aumentar `client_max_body_size` no nginx.conf

### 504 Gateway Timeout
- Processamento > 300s (API) ou 600s (Upload)
- Solução: Aumentar `proxy_read_timeout` no nginx.conf

### Connection refused
- Nginx não conecta no FastAPI
- Solução: Verificar se FastAPI está em `api:8001` (Docker) ou `127.0.0.1:8002` (local)

---

## 📝 Checklist Migração

- [x] Criar nginx.conf para Docker
- [x] Criar nginx.conf.local para desenvolvimento
- [ ] Testar Docker Compose
- [ ] Testar Nginx local
- [ ] Parar Laravel
- [ ] Verificar frontends (Welcome, RAG Console, Admin)
- [ ] Testar upload de documentos
- [ ] Testar busca RAG
- [ ] Monitorar performance
- [ ] Ajustar workers/timeouts se necessário

---
**Data**: 2025-11-01
