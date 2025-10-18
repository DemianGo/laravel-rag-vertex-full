# 🔄 **PLANEJAMENTO: MIGRAÇÃO SQLITE → POSTGRESQL**

## 📋 **SITUAÇÃO ATUAL**

### **Arquitetura Híbrida Atual:**
```
Laravel (PHP) → SQLite (dados básicos)
Python RAG → PostgreSQL (embeddings + busca vetorial)
```

### **Problemas Identificados:**
1. **Inconsistência:** 2 bancos diferentes
2. **Complexidade:** Configurações duplicadas
3. **Manutenção:** Dificuldade para backup/restore
4. **Escalabilidade:** Limitações do SQLite

---

## 🎯 **OBJETIVO DA MIGRAÇÃO**

### **Arquitetura Unificada:**
```
Laravel (PHP) → PostgreSQL (todos os dados)
Python RAG → PostgreSQL (embeddings + busca vetorial)
```

### **Benefícios:**
1. **Consistência:** Um banco para tudo
2. **Performance:** PostgreSQL otimizado para RAG
3. **Escalabilidade:** Suporte a concorrência avançada
4. **Backup:** Estratégia única de backup
5. **Manutenção:** Configuração simplificada

---

## 📊 **ANÁLISE DE DADOS ATUAIS**

### **SQLite (database.sqlite):**
- **Tabelas:** 21 tabelas
- **Registros:** ~100+ registros totais
- **Tamanho:** ~50MB (estimado)
- **Dados principais:**
  - users: 2 registros
  - documents: 17 registros
  - chunks: 72 registros
  - plan_configs: 3 registros
  - subscriptions: 3 registros
  - payments: 3 registros
  - ai_provider_configs: 8 registros
  - system_configs: 12 registros

### **PostgreSQL (se existir):**
- **Status:** Configurado mas não usado ativamente
- **Embeddings:** Provavelmente vazios ou desatualizados

---

## 🔧 **ETAPAS DA MIGRAÇÃO**

### **FASE 1: PREPARAÇÃO (30 min)**
1. **Backup completo do SQLite**
2. **Configurar PostgreSQL local**
3. **Instalar pgvector extension**
4. **Criar database PostgreSQL**
5. **Configurar .env para PostgreSQL**

### **FASE 2: MIGRAÇÃO DE DADOS (45 min)**
1. **Executar migrations no PostgreSQL**
2. **Migrar dados do SQLite para PostgreSQL**
3. **Verificar integridade dos dados**
4. **Testar conexões**

### **FASE 3: AJUSTES DE CÓDIGO (60 min)**
1. **Atualizar scripts Python para usar PostgreSQL**
2. **Ajustar queries SQLite → PostgreSQL**
3. **Configurar pgvector para embeddings**
4. **Testar RAG search**

### **FASE 4: TESTES (45 min)**
1. **Testar upload de documentos**
2. **Testar RAG search**
3. **Testar admin panel**
4. **Testar sistema de pagamentos**
5. **Testar todas as funcionalidades**

### **FASE 5: LIMPEZA (15 min)**
1. **Remover configurações SQLite desnecessárias**
2. **Atualizar documentação**
3. **Limpar arquivos temporários**

---

## 📋 **DETALHAMENTO TÉCNICO**

### **1. CONFIGURAÇÃO POSTGRESQL**

```bash
# Instalar PostgreSQL + pgvector
sudo apt update
sudo apt install postgresql postgresql-contrib
sudo apt install postgresql-server-dev-14
pip install pgvector

# Criar database
sudo -u postgres createdb laravel_rag
sudo -u postgres psql -c "CREATE EXTENSION vector;" laravel_rag
```

### **2. ATUALIZAÇÃO .env**

```bash
# Antes (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/laravel-rag-vertex-full/database/database.sqlite

# Depois (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_rag
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

### **3. MIGRAÇÃO DE DADOS**

```bash
# Executar migrations
php artisan migrate:fresh

# Importar dados do SQLite
php artisan tinker --execute="
// Script para migrar dados
// (será criado durante a migração)
"
```

### **4. AJUSTES PYTHON**

```python
# scripts/rag_search/config.py
DB_CONFIG = {
    "host": "127.0.0.1",
    "database": "laravel_rag", 
    "user": "postgres",
    "password": "postgres",
    "port": "5432"
}
```

---

## ⚠️ **RISCOS E MITIGAÇÕES**

### **Riscos Identificados:**

1. **Perda de dados**
   - **Mitigação:** Backup completo antes da migração
   - **Rollback:** Manter SQLite como backup

2. **Quebra de funcionalidades**
   - **Mitigação:** Testes extensivos após migração
   - **Rollback:** Reverter .env para SQLite

3. **Performance diferente**
   - **Mitigação:** Configurar PostgreSQL otimizado
   - **Monitoramento:** Acompanhar métricas

4. **Scripts Python quebrados**
   - **Mitigação:** Atualizar configurações Python
   - **Teste:** Verificar RAG search

### **Plano de Rollback:**
```bash
# Se algo der errado, reverter para SQLite
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/laravel-rag-vertex-full/database/database.sqlite
```

---

## 📊 **ESTIMATIVA DE TEMPO**

| Fase | Tempo | Descrição |
|------|-------|-----------|
| Preparação | 30 min | Backup, config PostgreSQL |
| Migração | 45 min | Migrar dados, executar migrations |
| Ajustes | 60 min | Código Python, queries |
| Testes | 45 min | Testar todas funcionalidades |
| Limpeza | 15 min | Documentação, limpeza |
| **TOTAL** | **3h 15min** | **Migração completa** |

---

## 🎯 **CRITÉRIOS DE SUCESSO**

### **Funcionalidades que DEVEM funcionar após migração:**
1. ✅ **Login/Registro** de usuários
2. ✅ **Upload de documentos** (todos os formatos)
3. ✅ **RAG Search** (Python + PHP)
4. ✅ **Admin Panel** (todas as funcionalidades)
5. ✅ **Sistema de Pagamentos** (Mercado Pago)
6. ✅ **API Endpoints** (todos os 48+ endpoints)
7. ✅ **Vídeo Processing** (transcrição)
8. ✅ **Excel Estruturado** (agregações)

### **Métricas de Performance:**
- **RAG Search:** < 10s (atual: ~6s)
- **Upload:** < 60s para PDFs grandes
- **Admin Panel:** < 2s para carregar páginas
- **API Response:** < 3s para endpoints

---

## 🚀 **PRÓXIMOS PASSOS**

1. **Aprovação do planejamento**
2. **Execução da migração**
3. **Testes extensivos**
4. **Documentação atualizada**
5. **Deploy em produção**

---

## ❓ **PERGUNTAS PARA APROVAÇÃO**

1. **Você aprova este planejamento?**
2. **Alguma funcionalidade específica que precisa de atenção especial?**
3. **Horário preferido para a migração?**
4. **Alguma preocupação específica?**

---

**📅 Data do Planejamento:** 2025-10-17 15:45 UTC  
**⏱️ Tempo Estimado:** 3h 15min  
**🎯 Objetivo:** Unificar arquitetura de banco de dados
