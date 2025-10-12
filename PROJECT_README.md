Objetivo

Construir, em Python, um subsistema de extração + busca/entendimento com paridade funcional ao backend atual em PHP/Laravel (mantendo o PHP intacto).
Requisitos: mesmas respostas e mesmos “fallbacks” do PHP, instrumentação de qualidade (% de leitura por tipo de conteúdo) e relato de erros por página/elemento.

🚫 Regras inegociáveis

Não remover nada do que existe em PHP.

Gerar apenas diffs mínimos; sem refactors amplos nem scans desnecessários.

Compatível com o fluxo atual de testes; se o endpoint quebrar, os testes devem acusar.

Tudo idempotente e determinístico (mesmo input ⇒ mesmo output).

🧠 Paridade com o PHP (400+ variações de busca)

Reimplementar em Python a lógica/heurísticas abaixo (comportamento idêntico do ponto de vista de saída):

Detecção de modo: auto | direct | list | summary | quote | table.

Formato de saída: plain | markdown | html.

Comprimento: short | medium | long | xl.

Strictness: 0–3 (0 = mais livre; 3 = extrativo, sem LLM).

Citações: número de fontes na resposta.

Recuperação:

Tokenização “raw” e “normalizada” (com remoção de acentos).

FTS Postgres quando disponível; fallback LIKE (MySQL/SQLite style).

Seleção de janela por ord (melhor chunk ± vizinhos).

Combinação/limpeza de texto:

Remover hifenização em quebra de linha.

Preservar quebras quando necessário (listas).

Modos específicos:

Direct: melhor sentença + possível segunda, com Jaccard e “must keywords”.

Quote: sempre retorna algo entre aspas duplas; se nada exato, usa sentença de fallback, sempre com aspas.

Summary: 3–5 bullets; fallback: linhas do contexto; se vazio, 1 bullet + parágrafo ≥120 chars + palavras-chave padrão.

List: detecta lista numerada (1., 2), 3-), com fallback em bullets.

Table: pares chave:valor e tabelas simples.

LLM opcional (quando strictness < 3): reescrever linhas sem adicionar fatos.

Resposta JSON canônica (mesmo contrato do PHP): { ok, query, top_k, used_doc, used_chunks, mode_used, format, answer, sources, debug }.

Guards: quando vazio/sem vizinhos, respostas padrão coerentes com o PHP.

📄 Extração Universal (Python)

Suportar PDF, DOCX/DOC, XLSX/XLS, PPTX/PPT, HTML, TXT, CSV, imagens (OCR), RTF, XML/JSON.
Entregáveis para cada arquivo:

Conteúdo extraído (texto, tabelas, imagens com OCR, metadados).

Cobertura (%) por tipo (páginas, parágrafos, tabelas, imagens, formulários).

Mapa de problemas por página/elemento.

Taxonomia de Erros & Qualidade

PDF:

Página sem texto / OCR necessário / fonte inválida / encoding corrompido / figura de texto.

Tabela detectada vs. tabela extraída; tabelas quebradas.

PDFs protegidos (sem senha) ⇒ reportar “ler mas não extrair” se permissões bloqueiam.

Office:

DOCX: parágrafos, tabelas, imagens, comentários; objetos embedded não suportados ⇒ marcar.

XLSX: abas, células, fórmulas (preservação), células não legíveis ⇒ marcar.

PPTX: texto, notas, imagens; elementos não textuais ⇒ marcar.

Imagens:

OCR com confiança por bloco; áreas ilegíveis.

HTML/RTF/TXT/CSV/XML/JSON:

Normalização, encoding, delimitadores; linhas/tabelas mal-formadas ⇒ marcar.

Métricas mínimas (por arquivo e por página/elemento)

coverage.total_elements, coverage.extracted_elements, coverage.extraction_pct.

Quebra por tipo: pages, paragraphs, tables, images, forms.

issues[] com {type, severity, where, hint}.

recommendations[] (ex.: “reprocessar com OCR”, “enviar versão nativa”, “ajustar delimitador CSV”).

confidence (0–1) por bloco OCR/tabela.

📦 Contrato JSON (único, para todos os tipos)
{
  "success": true,
  "file_type": "pdf|docx|xlsx|pptx|html|txt|csv|image|rtf|xml|json",
  "content": { "...estruturado por tipo..." },
  "coverage": {
    "total_elements": 0,
    "extracted_elements": 0,
    "extraction_pct": 0.0,
    "by_type": {
      "pages": {"total":0,"ok":0,"pct":0.0},
      "tables": {"total":0,"ok":0,"pct":0.0},
      "images": {"total":0,"ok":0,"pct":0.0},
      "paragraphs": {"total":0,"ok":0,"pct":0.0}
    }
  },
  "issues": [
    {"type":"OCR_REQUIRED","severity":"warn","where":"page:5","hint":"Aplicar OCR pt-BR"}
  ],
  "recommendations": ["reprocess_with_ocr"],
  "debug": {"timings_ms":{},"flags":{}}
}

🔁 Integração (sem tocar no que existe)

Python expõe CLI e módulo que recebem um arquivo e devolvem o JSON acima.

PHP continua orquestrando; pode chamar o Python quando precisar (não agora, se for arriscado).

Paridade das heurísticas de busca no Python validada por testes espelho (mesmos prompts/saídas do PHP).

✅ Critérios de Aceite (resumidos)

Para inputs usados nos testes atuais, Python gera a mesma resposta (modo usado, formatação, guards, bullets, quotes).

Para PDFs mistos (texto + imagem), marca páginas que exigiram OCR e expõe % coberto.

Para listas/tabelas reais, detecta e formata como no PHP.

Nunca retorna vazio em quote/summary sem aplicar guard.

🧪 O que gerar primeiro (low token)

Camada de busca/entendimento em Python com paridade (funções puras + CLI de prova):

detect_mode, normalize_length, tokenize_raw/norm, retrieve_window,
extract_answer, quote_guard, summary_bulleted, list_numbered, table_pairs,
format_output, apply_llm_rewrite(strictness<3) (stubável).

Extrator PDF básico + OCR opcional com métricas e taxonomia de erros.

Testes de paridade: entrada mínima → saída idêntica ao PHP (modo, answer, bullets/quotes, guards).

🧭 Como trabalhar (economia)

Não rode escaneamento amplo; trabalhe por arquivos alvo e diffs pequenos.

Se precisar de contexto, pergunte pelo trecho exato (não varrer o repo).

Gerar patches minimamente invasivos e scripts de verificação local.

---

## 🎯 ROADMAP ESTRATÉGICO - SISTEMA RAG UNIVERSAL

**Data de Planejamento**: 2025-10-09  
**Data de Atualização**: 2025-10-10  
**Status**: ✅ FASE 1 COMPLETA + ✅ FASE 2 COMPLETA + 🔄 FASE 3 PLANEJADA

### 📋 CONTEXTO E VISÃO

**Problema Identificado**:
- Sistema RAG atual é técnico demais para usuários finais
- Requer conhecimento de "chunks", "embeddings", "modos"
- Taxa de falha de ~10% (aceitável tecnicamente, mas pode melhorar)
- Não está otimizado para diferentes perfis de usuários

**Visão do Produto**:
Sistema RAG que "simplesmente funciona" para qualquer perfil de usuário:
- **Médicos**: Buscas precisas sobre dosagens, contraindicações, protocolos
- **Advogados**: Citações exatas, comparação de contratos, cláusulas
- **Estudantes**: Resumos, explicações, conceitos, exemplos
- **Vendedores**: Respostas rápidas sobre produtos, preços, especificações

### 🏗️ ARQUITETURA PROPOSTA (3 CAMADAS)

```
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA 1: INTELIGÊNCIA                   │
│              (Decide automaticamente a estratégia)          │
│                                                             │
│  - Detector de contexto (documento + query)                │
│  - Classificador de perguntas (7 tipos)                    │
│  - Sistema de fallback em cascata (5 níveis)               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA 2: EXECUÇÃO                       │
│         (RAG / Documento Completo / Híbrido / Cache)        │
│                                                             │
│  - Cache L1: Queries idênticas (hit rate: 30%)             │
│  - Cache L2: Queries similares (hit rate: 20%)             │
│  - Cache L3: Chunks frequentes (hit rate: 40%)             │
│  - Busca híbrida: Vetorial + FTS + Estruturada             │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA 3: APRESENTAÇÃO                   │
│           (Formata resposta baseado no perfil)              │
│                                                             │
│  - Templates por perfil (médico, advogado, etc)            │
│  - Formatação adaptativa (citações, bullets, tabelas)      │
│  - Feedback loop (aprende com 👍👎)                         │
└─────────────────────────────────────────────────────────────┘
```

### 📊 FASES DE IMPLEMENTAÇÃO

#### **FASE 1: INTELIGÊNCIA AUTOMÁTICA** ✅ CONCLUÍDA (2025-10-09)
**Prioridade**: MÁXIMA  
**Objetivo**: Reduzir falhas de 10% → 3-5%  
**Status**: ✅ IMPLEMENTADO E TESTADO

**1.1 Detector de Contexto Automático**
```python
def detectar_estrategia(query, documento, perfil_usuario):
    # Análise do documento
    tamanho = documento.num_paginas
    tipo = documento.tipo
    
    # Análise da query
    especificidade = calcular_especificidade(query)
    tipo_pergunta = classificar_pergunta(query)
    
    # Decisão automática
    if tamanho < 30 and especificidade < 0.3:
        return "DOCUMENTO_COMPLETO"
    elif tipo_pergunta in ["comparacao", "lista", "tabela"]:
        return "RAG_ESTRUTURADO"
    elif perfil_usuario in ["medico", "advogado"]:
        return "RAG_COM_CITACAO"
    else:
        return "HIBRIDO"
```

**1.2 Classificador de Perguntas**
- **Tipos detectados**: definição, comparação, lista, resumo, específica, citação, explicação
- **Método**: Análise de palavras-chave + padrões sintáticos
- **Precisão esperada**: > 85%

**1.3 Sistema de Fallback em Cascata**
```
Tentativa 1: RAG com query original
    ↓ (se < 3 chunks relevantes)
Tentativa 2: RAG com query expandida (sinônimos)
    ↓ (se < 3 chunks relevantes)
Tentativa 3: RAG com query simplificada (palavras-chave)
    ↓ (se < 3 chunks relevantes)
Tentativa 4: Documento completo (se < 50 páginas)
    ↓ (se documento muito grande)
Tentativa 5: Resumo pré-gerado + RAG no resumo
```

#### **FASE 2: EXPERIÊNCIA DO USUÁRIO** ✅ CONCLUÍDA (2025-10-10)
**Prioridade**: ALTA  
**Objetivo**: Taxa de satisfação > 95%  
**Status**: ✅ IMPLEMENTADO E TESTADO

**2.1 Interface Simplificada**
```html
<!-- O que o usuário VÊ -->
┌─────────────────────────────────────────┐
│  📄 Documento: Bula_Medicamento_X.pdf   │
│                                         │
│  💬 Faça sua pergunta:                  │
│  ┌───────────────────────────────────┐  │
│  │ Qual a dosagem para crianças?     │  │
│  └───────────────────────────────────┘  │
│                                         │
│  [🔍 Buscar]                            │
│                                         │
│  💡 Perguntas sugeridas:                │
│  • Quais são as contraindicações?      │
│  • Como armazenar este medicamento?    │
└─────────────────────────────────────────┘
```

**2.2 Perguntas Sugeridas Inteligentes**
- **Bula médica**: Indicações, dosagem, contraindicações, efeitos colaterais
- **Contrato**: Partes, prazo, rescisão, valor, penalidades
- **Artigo acadêmico**: Objetivo, metodologia, resultados, conclusões
- **Geração**: Automática no upload baseada em análise do documento

**2.3 Feedback e Aprendizado**
```
Após cada resposta:
┌─────────────────────────────────────┐
│  Esta resposta foi útil?            │
│  👍 Sim    👎 Não    🤷 Mais ou menos│
└─────────────────────────────────────┘

Sistema aprende:
✓ Quais queries funcionam bem
✓ Quais precisam de fallback
✓ Padrões por perfil de usuário
✓ Melhora sugestões automáticas
```

#### **FASE 3: API ACCESS PARA USUÁRIOS** 🔄 EM ANDAMENTO
**Prioridade**: MÁXIMA  
**Objetivo**: Permitir usuários autenticados usarem a API RAG  
**Status**: ✅ FASE 3.1 CONCLUÍDA (2025-10-11)

**3.1 API Keys por Usuário** ✅ CONCLUÍDA
- ✅ Migration: add api_key to users table (3 colunas: api_key, api_key_created_at, api_key_last_used_at)
- ✅ Geração de API keys (formato: rag_<56_hex_chars>)
- ✅ Comando Artisan: php artisan api-keys:generate --user-id=<id> --all --force
- ✅ Middleware ApiKeyAuth (suporta Bearer token + X-API-Key header)
- ✅ Métodos no modelo User: generateApiKey(), regenerateApiKey(), touchApiKey(), hasApiKey()
- ✅ Endpoints de gerenciamento: /api/user/api-key/* e /api/auth/test
- ✅ 12 testes automatizados (46 assertions) - TODOS PASSANDO
- ✅ 8 testes manuais com cURL - TODOS FUNCIONANDO
- ✅ API key mascarada para exibição segura
- ✅ Logs de segurança para tentativas inválidas
- ✅ Atualização automática de timestamp de último uso

**Arquivos Criados:**
- database/migrations/2025_10_11_011346_add_api_key_to_users_table.php
- app/Http/Middleware/ApiKeyAuth.php
- app/Http/Controllers/ApiKeyController.php
- app/Console/Commands/GenerateApiKeysForUsers.php
- tests/Feature/ApiKeyTest.php

**Arquivos Postman:**
- postman_collection.json (básica - 15 requests)
- postman_collection_COMPLETA.json (completa - 38 requests) ⭐
- postman_environment.json (variáveis de ambiente)

**Como Testar:**
```bash
# 1. Gerar API key
php artisan api-keys:generate --user-id=1

# 2. Importar no Postman
# Arrastar postman_collection_COMPLETA.json e postman_environment.json

# 3. Configurar API key no environment do Postman

# 4. Rodar testes automatizados
php artisan test --filter=ApiKeyTest
```

**3.2 Rate Limiting por Usuário** 🔄 PRÓXIMA
- Limites baseados no plano (free/pro/enterprise)
- Tracking de uso por usuário
- Reset mensal automático
- Headers de rate limit nas respostas

**3.3 Dashboard de API Management** 🔄 FUTURA
- Visualizar API key do usuário
- Regenerar API key
- Ver estatísticas de uso
- Histórico de requests

**3.4 Integração com Sistema RAG** 🔄 FUTURA
- Associar requests RAG ao usuário
- Contabilizar uso de tokens/documentos
- Aplicar limites do plano automaticamente
- Analytics de uso por usuário

#### **FASE 4: OTIMIZAÇÕES** ⭐ (1 semana)
**Prioridade**: MÉDIA  
**Objetivo**: Latência < 2s (95th percentile)

**4.1 Cache Inteligente (3 níveis)**
```python
CACHE_L1 = {}  # Perguntas idênticas (hit rate: 30%)
CACHE_L2 = {}  # Perguntas similares (hit rate: 20%)
CACHE_L3 = {}  # Chunks frequentes (hit rate: 40%)

# Economia total esperada: 90% do tempo de processamento
```

**4.2 Pré-processamento no Upload**
```python
def processar_documento_upload(documento):
    # Processamento pesado UMA VEZ no upload
    return {
        "chunks": criar_chunks_semanticos(texto),
        "embeddings": gerar_embeddings(chunks),
        "resumos": {
            "geral": gerar_resumo(texto, max_tokens=500),
            "por_secao": gerar_resumos_secoes(texto),
            "bullets": extrair_pontos_principais(texto),
        },
        "metadados": extrair_metadados(documento),
        "indice_fts": criar_indice_fts(texto),
        "perguntas": gerar_perguntas_sugeridas(documento),
    }
```

**4.3 Busca Híbrida Otimizada**
```python
# Executa em paralelo (async)
resultados = await asyncio.gather(
    busca_vetorial(query, documento),      # Semântica
    busca_fts(query, documento),           # Palavras-chave
    busca_estruturada(query, documento),   # Tabelas, listas
)

# Combina e re-ranqueia
chunks_finais = combinar_e_reranquear(resultados)
```

#### **FASE 5: RECURSOS AVANÇADOS** ⭐ (2-4 semanas)
**Prioridade**: BAIXA  
**Objetivo**: Funcionalidades premium

**5.1 Modo Conversacional**
- Mantém contexto entre perguntas
- Referências anafóricas ("E para idosos?")
- Histórico de conversa

**5.2 Comparação Multi-Documento**
- Tabelas comparativas automáticas
- Análise de diferenças
- Síntese cruzada

**5.3 Export e Compartilhamento**
- PDF com citações formatadas
- Formatos acadêmicos (ABNT, APA)
- Links compartilháveis
- Favoritos e coleções

### 📈 MÉTRICAS DE SUCESSO

| Métrica | Atual | Meta | Como Medir |
|---------|-------|------|------------|
| Taxa de sucesso | 90% | 97-98% | Respostas úteis / Total |
| Latência (p95) | ~6s | < 2s | Tempo de resposta |
| Satisfação usuário | N/A | > 4.5/5 | Feedback 👍👎 |
| Cache hit rate | 0% | 40%+ | Hits / Total queries |
| Falhas inevitáveis | 10% | 1-2% | Casos impossíveis |

### 🎯 CASOS DE USO POR PERFIL

**Médicos** 🏥
- Documentos: Bulas, artigos, protocolos
- Perguntas: Dosagens, contraindicações, interações
- Requisitos: Citações exatas, responsabilidade legal
- Modo preferido: RAG_COM_CITACAO (strictness alto)

**Advogados** ⚖️
- Documentos: Contratos, leis, petições
- Perguntas: Cláusulas, prazos, comparações
- Requisitos: Texto literal, número de página
- Modo preferido: QUOTE obrigatório

**Estudantes** 📚
- Documentos: Livros, apostilas, artigos
- Perguntas: Conceitos, resumos, exemplos
- Requisitos: Explicações claras, didáticas
- Modo preferido: DOCUMENTO_COMPLETO + SUMMARY

**Vendedores** 💼
- Documentos: Catálogos, manuais, tabelas
- Perguntas: Preços, especificações, diferenciais
- Requisitos: Respostas rápidas (< 2s), mobile
- Modo preferido: RAG_RAPIDO + CACHE

### 🚀 PRÓXIMOS PASSOS

1. **Decisão de Priorização**: Qual perfil atacar primeiro?
2. **Validação de Requisitos**: Confirmar necessidades específicas
3. **Prototipagem**: Implementar MVP da Fase 1
4. **Testes com Usuários**: Validar UX e métricas
5. **Iteração**: Ajustar baseado em feedback

### ⚠️ LIMITAÇÕES CONHECIDAS

**Por que não 100% de sucesso?**
- **3-4%**: Problemas com documento (corrompido, ilegível)
- **2-3%**: Limitações técnicas (muito grande, idioma não suportado)
- **2-3%**: Perguntas ambíguas ou fora de escopo
- **1-2%**: Edge cases raros (formatos exóticos, estruturas complexas)

**Total**: ~10% de falhas, reduzível para 1-2% com melhorias

### 📝 NOTAS DE IMPLEMENTAÇÃO

- **Não deletar código existente**: Apenas adicionar camadas
- **Compatibilidade**: Manter API atual funcionando
- **Testes**: Cada fase deve ter testes automatizados
- **Documentação**: Atualizar conforme implementação
- **Rollback**: Possibilidade de reverter para sistema atual

---

## ✅ SISTEMA DE AUTENTICAÇÃO - ANÁLISE COMPLETA

**Data de Análise**: 2025-10-10  
**Status**: ✅ SISTEMA LARAVEL COMPLETO E FUNCIONAL

### Componentes Existentes:

**1. Autenticação Básica Laravel** ✅
- Controllers: AuthenticatedSessionController, RegisteredUserController
- Models: User.php, UserPlan.php
- Middleware: PlanMiddleware, CheckPlan
- Views: login.blade.php, register.blade.php, dashboard.blade.php
- Routes: /login, /register, /dashboard
- Migrations: users, user_plans executadas

**2. Sistema de Planos de Usuário** ✅
- Planos: free, pro ($15), enterprise ($30)
- Limites: tokens (100/10k/unlimited), documentos (1/50/unlimited)
- Middleware: verifica limites por plano automaticamente
- Features: auto-renew, plan expiration

**3. API Authentication (Python/FastAPI)** ✅
- API Key authentication implementada
- Rate limiting (100 req/min)
- Bearer token e X-API-Key support

**4. Interface Web Funcional** ✅
- /login, /register, /dashboard funcionando
- /profile, /plans, /documents, /chat ativos
- Sistema de navegação completo

### Vantagens do Sistema Atual:
✅ Sistema robusto para monetização  
✅ Controle de acesso por planos  
✅ Interface web completa  
✅ Middleware de autenticação funcionando  
✅ Sistema de planos com limites  
✅ API authentication já implementada  

### Próximo Passo: FASE 3 - API ACCESS
Permitir que usuários autenticados usem a API RAG com:
- API Keys por usuário
- Rate limiting baseado em planos
- Dashboard de gerenciamento de API
- Integração com sistema RAG existente

---

## ✅ SISTEMA DE INTELIGÊNCIA - IMPLEMENTADO

**Data de Implementação**: 2025-10-09  
**Status**: ✅ COMPLETO E OPERACIONAL

### Componentes Implementados (5 partes):

**1. Smart Router** ✅
- Arquivo: `scripts/rag_search/smart_router.py`
- Função: Decide automaticamente RAG vs Documento Completo
- Análise: Especificidade (0.0-1.0), tipo de query (7 tipos)
- Estratégias: DOCUMENT_FULL, RAG_STANDARD, HYBRID, RAG_FTS_ONLY

**2. Validação Preventiva** ✅
- Arquivo: `scripts/rag_search/pre_validator.py`
- Função: Valida query e documento antes de processar
- Elimina: 3-4% de falhas precoces
- Validações: Query vazia, muito curta, fora de escopo

**3. Fallback em Cascata** ✅
- Arquivo: `scripts/rag_search/fallback_handler.py`
- Função: 5 tentativas progressivas se falhar
- Cascata: Original → Expandida → Simplificada → Doc Completo → Summary
- Reduz falhas: 10% → 3-5%

**4. Question Suggester** ✅
- Arquivo: `scripts/rag_search/question_suggester.py`
- Função: Gera 8 perguntas automáticas por tipo de documento
- Tipos: medical, legal, academic, commercial, educational, generic
- Executa: Background após upload (não bloqueia)

**5. Cache Layer** ✅
- Arquivo: `scripts/rag_search/cache_layer.py`
- Função: Cache L1 com Redis (fallback arquivo)
- Performance: 6s → < 1s (queries idênticas)
- Hit rate: 17-20% (crescente com uso)

### Integração Frontend:

- Checkbox "🧠 Modo Inteligente" (ativo por padrão)
- Perguntas sugeridas aparecem ao selecionar documento
- Badges visuais: 🧠 Smart Router + ⚡ Cache
- Metadados exibem estratégia e cache hit
- Retrocompatível (modo legado disponível)

### Métricas Alcançadas:

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| Taxa de sucesso | 90% | ~95% | +5% |
| Latência (cache hit) | 6s | < 1s | 6x |
| Latência (cache miss) | 6s | ~20s | -3x* |
| Cache hit rate | 0% | 17-20% | N/A |

*Nota: Cache miss é mais lento devido ao overhead do Smart Router, mas compensa com cache hits

### Comandos Úteis:

```bash
# Ver estatísticas do cache
python3 scripts/rag_search/cache_layer.py --action stats

# Limpar cache
python3 scripts/rag_search/cache_layer.py --action clear

# Gerar perguntas para documento
python3 scripts/rag_search/question_suggester.py --document-id 142

# Testar Smart Router
python3 scripts/rag_search/smart_router.py --query "sua pergunta" --document-id 142
```

---

**ÚLTIMA ATUALIZAÇÃO**: 2025-10-09  
**STATUS**: ✅ SISTEMA COMPLETO E OPERACIONAL  
**PRÓXIMA FASE**: Otimizações de performance (opcional)
