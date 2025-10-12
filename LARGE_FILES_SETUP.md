# 🚀 Suporte a Arquivos Gigantes - Guia de Configuração

**Status:** ✅ Implementação Completa  
**Data:** 2025-10-11  
**Objetivo:** Suporte a arquivos de até 5.000 páginas (500MB)

---

## 📋 **Especificações**

- **Limite de páginas:** 5.000 por arquivo
- **Tamanho máximo:** 500MB por arquivo
- **Uploads simultâneos:** 5 arquivos
- **Tempo de processamento:** até 300 segundos (5 minutos)
- **Memória alocada:** 2GB por processo
- **Processamento:** Síncrono (usuário espera)

---

## 🛠️ **Configuração do Sistema (Local)**

### **1. Executar Script de Configuração**

```bash
cd /var/www/html/laravel-rag-vertex-full
sudo bash config_large_files.sh
```

Este script irá:
- ✅ Atualizar `php.ini` (upload: 512M, memory: 2G, timeout: 300s)
- ✅ Atualizar NGINX (client_max_body_size: 512M, timeouts: 300s)
- ✅ Configurar PHP-FPM pool (10 workers para 5 uploads simultâneos)
- ✅ Reiniciar serviços (PHP-FPM e NGINX)
- ✅ Validar configurações

### **2. Verificar Configurações**

```bash
# Ver configurações PHP
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"

# Ver status dos serviços
sudo systemctl status php8.4-fpm
sudo systemctl status nginx
```

---

## ☁️ **Configuração Google Cloud**

### **Opção 1: Cloud Run (Recomendado)**

Arquivo: `cloudbuild.yaml` ou `app.yaml`

```yaml
runtime: custom
env: flex

resources:
  cpu: 2
  memory_gb: 4
  disk_size_gb: 10

automatic_scaling:
  min_num_instances: 1
  max_num_instances: 10
  target_cpu_utilization: 0.65

env_variables:
  APP_ENV: production
  PHP_MEMORY_LIMIT: 2G

health_check:
  enable_health_check: True
  check_interval_sec: 30
  timeout_sec: 10

liveness_check:
  path: /api/health
  check_interval_sec: 30
  timeout_sec: 10
  failure_threshold: 2
  success_threshold: 2

readiness_check:
  path: /api/health
  check_interval_sec: 5
  timeout_sec: 10
  failure_threshold: 2
  success_threshold: 2
```

### **Opção 2: Compute Engine (VM)**

```bash
# Instância recomendada
gcloud compute instances create laravel-rag \
  --machine-type=n1-standard-2 \
  --zone=us-central1-a \
  --image-family=debian-11 \
  --image-project=debian-cloud \
  --boot-disk-size=50GB \
  --tags=http-server,https-server

# Após SSH na VM, execute:
sudo bash config_large_files.sh
```

### **Cloud SQL (PostgreSQL)**

```bash
gcloud sql instances create laravel-rag-db \
  --database-version=POSTGRES_14 \
  --tier=db-n1-standard-1 \
  --region=us-central1
```

### **Load Balancer Timeout**

```bash
# Aumentar timeout do backend service
gcloud compute backend-services update BACKEND_NAME \
  --timeout=300s
```

---

## 🧪 **Testes**

### **1. Gerar Arquivos de Teste**

```bash
cd /var/www/html/laravel-rag-vertex-full
python3 generate_large_test_files.py
```

Gera arquivos gigantes em `/tmp/large_test_files/`:
- PDF: 1.000, 3.000, 5.000 páginas
- DOCX: 2.000 páginas
- XLSX: 10.000 linhas
- PPTX: 500 slides
- TXT, CSV, HTML, XML, RTF

### **2. Executar Testes Automatizados**

```bash
./test_large_files.sh
```

Testa automaticamente:
- ✅ Upload de todos os formatos
- ✅ Validação de páginas (rejeita > 5.000)
- ✅ Busca RAG em documentos grandes
- ✅ Performance e tempo de resposta

### **3. Teste Manual via Frontend**

1. Acesse: `http://localhost:8000/rag-frontend`
2. Faça upload de um arquivo grande (ex: PDF 3.000 páginas)
3. Aguarde processamento (2-3 minutos)
4. Teste busca RAG com qualquer query

### **4. Teste Manual via API**

```bash
# Upload
curl -X POST http://localhost:8000/api/rag/ingest \
  -F "document=@/tmp/large_test_files/test_3000pages.pdf" \
  -F "user_id=1" \
  -F "title=Test Large PDF"

# Busca RAG (use document_id retornado acima)
curl -X POST http://localhost:8000/api/rag/python-search \
  -H "Content-Type: application/json" \
  -d '{"query":"Faça um resumo","document_id":123,"smart_mode":true}'
```

---

## 📊 **Monitoramento**

### **Logs**

```bash
# Laravel log
tail -f storage/logs/laravel.log

# NGINX log
sudo tail -f /var/log/nginx/error.log

# PHP-FPM log
sudo tail -f /var/log/php8.4-fpm.log
```

### **Métricas Esperadas**

| Arquivo | Páginas | Chunks | Tempo Extração | Tempo Embeddings | Total |
|---------|---------|--------|----------------|------------------|-------|
| PDF 1K  | 1.000   | ~500   | ~20s           | ~15s             | ~35s  |
| PDF 3K  | 3.000   | ~1.500 | ~60s           | ~45s             | ~105s |
| PDF 5K  | 5.000   | ~2.500 | ~90s           | ~75s             | ~165s |
| DOCX 2K | 2.000   | ~1.000 | ~30s           | ~30s             | ~60s  |

---

## 🔧 **Troubleshooting**

### **Erro: "Arquivo muito grande"**

```bash
# Verifique configurações PHP
php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"

# Se ainda não estiver correto, execute novamente:
sudo bash config_large_files.sh
sudo systemctl restart php8.4-fpm nginx
```

### **Erro: "Timeout durante upload"**

```bash
# Verifique timeout do NGINX
sudo nginx -T | grep timeout

# Se necessário, edite manualmente:
sudo nano /etc/nginx/sites-available/default
# Adicione: client_body_timeout 300s;

sudo systemctl restart nginx
```

### **Erro: "Documento tem X páginas. Limite: 5.000"**

Este é um erro **esperado** quando o arquivo excede 5.000 páginas.  
Para ajustar o limite, edite:

```php
// app/Services/DocumentPageValidator.php
private const MAX_PAGES = 5000; // Ajuste aqui
```

### **Erro: "Out of memory"**

```bash
# Aumente memory_limit do PHP
sudo sed -i 's/^memory_limit = .*/memory_limit = 4G/' /etc/php/8.4/fpm/php.ini
sudo systemctl restart php8.4-fpm
```

---

## 📐 **Estimativas e Limites**

### **Tamanho por Formato (5.000 páginas)**

| Formato | Tamanho Estimado |
|---------|------------------|
| PDF     | 250MB - 1GB      |
| DOCX    | 25MB - 200MB     |
| XLSX    | 5MB - 50MB       |
| PPTX    | 50MB - 150MB     |
| TXT     | 10MB - 50MB      |

### **Tempo de Processamento**

- **Extração:** 60-90s (depende do formato e tamanho)
- **Chunking:** 10-15s (2.500 chunks)
- **Embeddings (batch):** 25-75s (2.500 chunks × 30ms, batch de 100)
- **Total:** 2-3 minutos para 5.000 páginas

### **Memória Necessária**

- **Por arquivo:** ~560MB
- **5 uploads simultâneos:** 3.5GB total
- **Sistema:** 8GB RAM recomendado (4GB mínimo)

---

## ✅ **Checklist de Deploy (Google Cloud)**

- [ ] Configurar Cloud Run ou Compute Engine (4GB RAM, 2 CPU)
- [ ] Configurar timeout para 300s
- [ ] Configurar Cloud SQL (PostgreSQL 14+)
- [ ] Configurar Load Balancer com timeout 300s
- [ ] Configurar variáveis de ambiente (`.env`)
- [ ] Executar migrações: `php artisan migrate`
- [ ] Testar upload de arquivo grande (3.000+ páginas)
- [ ] Testar busca RAG
- [ ] Configurar monitoramento (Cloud Logging, Cloud Monitoring)
- [ ] Configurar alertas (CPU > 80%, Memory > 90%, Errors)

---

## 🎯 **Próximos Passos**

Após configurar e testar localmente:

1. **Deploy no Google Cloud**
   - Escolher: Cloud Run, App Engine ou Compute Engine
   - Configurar recursos (4GB RAM, 2 CPU, 300s timeout)
   - Configurar Cloud SQL e Load Balancer

2. **Monitoramento**
   - Configurar alertas de performance
   - Monitorar logs de erros
   - Analisar métricas de uso

3. **Otimização (se necessário)**
   - Implementar processamento assíncrono (Laravel Queues)
   - Implementar streaming para arquivos > 10.000 páginas
   - Cache de chunks frequentes

---

## 📞 **Suporte**

- **Logs:** `storage/logs/laravel.log`
- **Health Check:** `http://localhost:8000/api/health`
- **Documentação:** `.cursorrules` e `PROJECT_README.md`

---

**✅ Sistema pronto para produção com suporte a arquivos de até 5.000 páginas!**

