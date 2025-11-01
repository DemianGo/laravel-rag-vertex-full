# 🎯 OPÇÕES CORRETAS - FastAPI NÃO serve HTML estático

## ❌ NUNCA: FastAPI servindo HTML estático
FastAPI só serve API. HTML estático = outro servidor.

---

## ✅ OPÇÃO 1: Docker Compose (LOCAL/DEV)
**Nginx + FastAPI + Redis + PostgreSQL**

```bash
cd scripts/docker
docker-compose --profile production up -d
```

**Arquitetura:**
- Nginx: HTML estático (public/)
- FastAPI: API (8001)
- Redis: Cache
- PostgreSQL: DB

**Escala local:** Limita-se à máquina.

---

## ✅ OPÇÃO 2: Cloud Run Multi-Service
**2 containers: Frontend + Backend**

### Container 1: Nginx (HTML estático)
```dockerfile
FROM nginx:alpine
COPY public/ /usr/share/nginx/html/
```

### Container 2: FastAPI (API)
```dockerfile
# Usar Dockerfile existente
FROM python:3.11-slim
# ... já pronto
```

**Deploy:**
```bash
# Frontend (Nginx)
gcloud run deploy rag-frontend \
  --source . \
  --dockerfile Dockerfile.frontend \
  --min-instances 10 \
  --max-instances 1000 \
  --cpu 2 \
  --memory 2Gi

# Backend (FastAPI)
gcloud run deploy rag-api \
  --source . \
  --dockerfile Dockerfile.backend \
  --min-instances 10 \
  --max-instances 1000 \
  --cpu 4 \
  --memory 8Gi \
  --set-env-vars DATABASE_URL=...
```

**Escala:** 0 → 1000 instâncias

---

## ✅ OPÇÃO 3: GKE com Nginx Ingress
**Kubernetes + Nginx + FastAPI + StatefulSets**

**Arquitetura:**
- Nginx Ingress: HTML + Load Balancer
- FastAPI Deployment: 10-50 pods (auto-scale)
- PostgreSQL StatefulSet
- Redis StatefulSet

**Escala:** Limitada pelos nós ($300/mês)

---

## 🎯 RECOMENDAÇÃO

**Começar com OPÇÃO 2 (Cloud Run Multi-Service)**

Por quê?
✅ Escala 0 → 1000
✅ Redução de custo quando vazio
✅ Nginx para HTML, FastAPI para API
✅ Orquestração pelo Load Balancer

**Fazer depois:** GKE se precisar de mais controle

---

## ❓ DECISÃO

A) Docker Compose (local/teste)
B) Cloud Run Multi-Service (produção)
C) GKE (máximo controle)

---
