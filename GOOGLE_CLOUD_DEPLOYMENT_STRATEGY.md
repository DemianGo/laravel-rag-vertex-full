# Estratégia de Deploy Google Cloud - PROFISSIONAL

## 🎯 Contexto
- **Projeto**: liberai-ai (Vertex AI + Gemini)
- **Ambiente**: Debian 11 local → Google Cloud production
- **Escala**: Milhares de usuários simultâneos
- **Arquitetura atual**: Laravel (porta 8000) + FastAPI (porta 8002)

---

## 🏗️ Arquitetura RECOMENDADA para GCP

### ✅ OPÇÃO PROFISSIONAL: Cloud Run + Cloud SQL + Cloud Storage

```
┌─────────────────────────────────────────────────────┐
│  Google Cloud Load Balancer (HTTPS)                │
└────────────────┬────────────────────────────────────┘
                 │
        ┌────────┴────────┐
        │                 │
        v                 v
┌───────────────┐  ┌───────────────┐
│ Cloud Run     │  │ Cloud Run     │
│ Frontend      │  │ Backend API   │
│ (Static HTML) │  │ (FastAPI)     │
│ Min: 10       │  │ Min: 10       │
│ Max: 1000     │  │ Max: 1000     │
│ CPU: 2        │  │ CPU: 2        │
│ RAM: 4GB      │  │ RAM: 8GB      │
└───────────────┘  └───────┬───────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        v                  v                  v
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ Cloud SQL     │  │ Cloud Redis   │  │ Cloud Storage │
│ PostgreSQL 14 │  │ MemoryStore   │  │ Buckets       │
│ 16GB RAM      │  │ 2GB           │  │ (uploads)     │
│ HA + Backups  │  │ HA Available  │  │ Multi-Region  │
└───────────────┘  └───────────────┘  └───────────────┘
        │
        v
┌───────────────┐
│ Vertex AI     │
│ Gemini API    │
│ Cloud Vision  │
└───────────────┘
```

### Vantagens:
✅ **Escala automática** (0 → 1000+ instâncias)
✅ **Pay-per-use** (zero instâncias = zero custo)
✅ **HA nativo** (99.95% SLA)
✅ **HTTPS automático** (Let's Encrypt)
✅ **CDN integrado** (Cloud CDN)
✅ **Monitoring** (Cloud Monitoring/Logging)
✅ **Security** (IAM, VPC, WAF)

---

## 🚀 OPÇÃO RÁPIDA: GKE (Kubernetes) com Nginx

```
┌─────────────────────────────────────────────────────┐
│  Google Kubernetes Engine                          │
│  Node Pool: 3-10 nodes (e2-standard-4)            │
└────────────────┬────────────────────────────────────┘
                 │
        ┌────────┴────────┐
        │                 │
        v                 v
┌───────────────┐  ┌───────────────┐
│ Nginx         │  │ FastAPI       │
│ Ingress       │  │ Deployment    │
│ + Cert-Manager│  │ HPA: 3-50 pods│
│               │  │ CPU: 2        │
│               │  │ RAM: 4GB      │
└───────────────┘  └───────┬───────┘
                           │
        ┌──────────────────┴──────────────────┐
        │                                     │
        v                                     v
┌───────────────┐                     ┌───────────────┐
│ Redis         │                     │ PostgreSQL    │
│ StatefulSet   │                     │ StatefulSet   │
└───────────────┘                     └───────────────┘
```

### Vantagens:
✅ **Controle total** (Kubernetes)
✅ **Docker Compose funciona** (kompose converter)
✅ **Custos previsíveis** (nodes fixos)
✅ **Nginx profissional**
❌ Complexidade Kubernetes
❌ Precisa manter clusters

---

## 📊 Comparação de Custo Estimado (mensal)

### Cloud Run (Pay-per-Use)
- **Free tier**: Primeiros 2M requests/mês
- **CPU**: $0.24/1000 vCPU-segundos
- **RAM**: $0.0025/GB-segundo
- **Requests**: $0.40/1M requests
- **Estimado (100k usuários)**: $200-500/mês

### GKE (Reserved)
- **3 nodes e2-standard-4**: $150/mês
- **Cloud SQL**: $100/mês
- **Redis**: $50/mês
- **Total**: $300/mês **fixo**

---

## 🎯 RECOMENDAÇÃO FINAL

### Para COMEÇAR (MVP/Lançamento):
**Cloud Run** - Escala automática, pay-per-use, zero manutenção

### Para CRESCER (10k+ usuários):
**GKE + Nginx** - Performance, controle, Kubernetes

---

## 📝 PRÓXIMOS PASSOS

**Escolha sua arquitetura e vamos implementar!**

1️⃣ **Cloud Run** (mais fácil)
2️⃣ **GKE + Nginx** (mais controle)
3️⃣ **Manter Docker Compose** (local/teste apenas)

---
**Data**: 2025-11-01
