# PROMPT DE ATUALIZAÇÃO COMPLETA DE ESTADO DO PROJETO

## INSTRUÇÕES PARA CURSOR IDE

Execute este prompt quando eu digitar: `/update-state` ou quando eu pedir "atualize o estado do projeto"

---

## ETAPA 1 - AUDITORIA COMPLETA DO CÓDIGO

**IMPORTANTE:** Analise cada arquivo lendo seu conteúdo completo. Não assuma nada baseado em nomes de arquivos.

### 1.1 Backend PHP

**Varra TODO o diretório recursivamente:**
- `app/Http/Controllers/`
- `app/Models/`
- `routes/`
- `config/`
- `database/migrations/`

**Para CADA arquivo `.php` encontrado:**

1. **Leia o arquivo completo** do início ao fim
2. **Identifique:**
   - Classes e métodos públicos
   - Funções implementadas (não apenas declaradas)
   - Comentários TODO, FIXME, ou "em desenvolvimento"
   - Imports/uses no topo do arquivo
   - Chamadas para outros componentes (Python, APIs externas)
3. **Determine status:**
   - ✅ **Completo:** Código implementado, sem TODOs, funciona
   - 🚧 **Parcial:** Funciona mas tem pendências ou TODOs
   - ❌ **Quebrado:** Erros sintaxe, funções vazias, imports faltando
   - 📝 **Stub:** Apenas estrutura, sem implementação

### 1.2 Python Scripts

**Varra TODO o diretório recursivamente:**
- `scripts/document_extraction/`
- `scripts/rag_search/`
- `scripts/api/`
- `scripts/pdf_extraction/`

**Para CADA arquivo `.py` encontrado:**

1. **Leia o arquivo completo** do início ao fim
2. **Identifique:**
   - Funções e classes definidas
   - Imports no topo (verifique se todos os módulos existem)
   - Implementação real vs funções vazias (`pass`, `raise NotImplementedError`)
   - Comentários TODO ou FIXME
   - Trechos comentados (código desabilitado)
3. **Teste mental de execução:**
   - Imports resolveriam?
   - Funções têm corpo implementado?
   - Retorna o que promete?
4. **Determine status:**
   - ✅ **Completo:** Implementado, imports OK, sem TODOs
   - 🚧 **Parcial:** Funciona mas precisa melhorias
   - ❌ **Quebrado:** Import faltando, função vazia, erros
   - 📝 **Stub:** Apenas estrutura

### 1.3 Frontend

**Varra TODO o diretório recursivamente:**
- `public/front/`
- `public/rag-frontend/`
- `resources/views/`
- `resources/js/`

**Para CADA pasta/arquivo de frontend:**

1. **Identifique tecnologia:**
   - HTML puro + JavaScript vanilla?
   - React (procure JSX, `import React`)?
   - Vue (procure `<template>`, `export default`)?
   - Outro framework?
2. **Verifique estrutura:**
   - Tem arquivo HTML principal (index.html, app.blade.php)?
   - Arquivos JavaScript presentes e implementados?
   - CSS existe e está completo?
   - Assets (imagens, fonts) referenciados existem?
3. **Analise integração com backend:**
   - Procure por `fetch()`, `axios.post()`, `$.ajax()`
   - Identifique URLs de API chamadas
   - Verifique se endpoints existem no backend
4. **Determine status:**
   - ✅ **Completo:** Interface funcional, conectada ao backend
   - 🚧 **Parcial:** Interface OK mas backend não conectado
   - ❌ **Incompleto:** Faltam arquivos ou implementação
   - 📝 **Protótipo:** Apenas estrutura HTML

### 1.4 Banco de Dados

**Analise migrations:**

1. **Leia CADA arquivo** em `database/migrations/`
2. **Para cada migration, identifique:**
   - Nome da tabela criada
   - Campos (nome, tipo, nullable)
   - Índices criados
   - Foreign keys
   - Timestamps (created_at, updated_at)
3. **Liste todas as tabelas** do projeto
4. **Verifique consistência:**
   - Models correspondem às tabelas?
   - Relacionamentos estão implementados?

### 1.5 Integrações e Fluxos

**Busque no código por padrões de integração:**

1. **PHP chamando Python:**
   - Busque por: `shell_exec()`, `exec()`, `system()`, `proc_open()`
   - Identifique: qual script Python? com quais parâmetros?
   - Verifique: script Python existe? está funcional?

2. **Frontend chamando Backend:**
   - Busque por: `fetch(`, `axios.`, `$.ajax`, `http.post`
   - Identifique: qual endpoint? método (GET/POST)?
   - Verifique: endpoint existe em routes/api.php?

3. **Backend acessando Banco:**
   - Busque por: `DB::`, `->where(`, `Model::find`
   - Verifique: tabelas e campos existem?

4. **Mapeie fluxo completo:**
   ```
   Frontend → Endpoint → Controller → Python Script → Database
   ```
   - Identifique cada etapa
   - Marque onde está quebrado ou faltando

---

## ETAPA 2 - ATUALIZAR .cursorrules

**Arquivo: `/var/www/html/laravel-rag-vertex-full/.cursorrules`**

### Instruções de Atualização:

1. **Leia o arquivo atual completamente**
2. **Mantenha estrutura existente** (árvores ASCII, emojis)
3. **Atualize APENAS os status baseado na auditoria**
4. **Adicione novos componentes descobertos**
5. **Remova seções obsoletas** (problemas já resolvidos)

### Regras de Movimentação:

**Mova para "🔒 NUNCA MODIFICAR" se:**
- Código completo ✅
- Testado e funcionando
- Sem TODOs ou pendências

**Mantenha em "🚧 EM DESENVOLVIMENTO" se:**
- Código parcial 🚧
- Funciona mas precisa melhorias
- Tem comentários "TODO" ou "FIXME"

**Crie seção "❌ QUEBRADO" se:**
- Código com erros
- Imports faltando
- Funções vazias ou stubs

### Formato Obrigatório:
```
🔒 NUNCA MODIFICAR (Descrição):
├── caminho/pasta/ ✅
│   ├── arquivo1.ext ✅ (o que faz)
│   └── arquivo2.ext ✅ (o que faz)
└── outro/caminho/ ✅

🚧 EM DESENVOLVIMENTO:
└── caminho/ 🚧 (o que falta fazer)

❌ QUEBRADO (corrigir urgente):
└── caminho/ ❌ (qual o problema)

---

ÚLTIMA AUDITORIA: [DATA ATUAL]
```

---

## ETAPA 3 - ATUALIZAR PROJECT_README.md

**Arquivo: `/var/www/html/laravel-rag-vertex-full/PROJECT_README.md`**

### Instruções de Atualização:

1. **Leia o arquivo atual completamente**
2. **Mantenha seções essenciais:**
   - O QUE É ESTE PROJETO
   - OBJETIVO FINAL
   - COMO USAR AGORA
   - CONFIGURAÇÃO
   - ARQUITETURA

3. **Atualize seção "## ESTADO ATUAL":**

```markdown
## ESTADO ATUAL ([DATA])

### ✅ FUNCIONANDO 100%

**Backend PHP:**
- [Liste APENAS componentes ✅ da auditoria]
- Seja específico: nome do arquivo + o que faz

**Python - Extração:**
- [Liste APENAS se todos os extractors funcionam]
- Especifique formatos suportados

**Python - RAG Search:**
- [Detalhe: busca vetorial OK? LLM OK? Threshold testado?]
- Status de CADA arquivo do rag_search/

**Frontend:**
- [Se não funciona, deixe em branco ou "Não implementado"]

### 🚧 EM PROGRESSO

**[Componente]:**
- O que funciona parcialmente
- O que falta implementar
- Bloqueadores (se houver)

### ❌ QUEBRADO / FALTANDO

**[Componente]:**
- Problema específico
- Erro que acontece
- O que precisa para funcionar

### 📊 ESTATÍSTICAS

- Total de arquivos PHP: [número]
- Total de arquivos Python: [número]
- Linhas de código: [aproximado]
- Endpoints API: [número]
- Migrations: [número]
- Chunks no banco: [número real do DB]
```

4. **Atualize "## PRÓXIMOS PASSOS":**

Baseado na auditoria, liste em ORDEM DE PRIORIDADE:

```markdown
## PRÓXIMOS PASSOS

1. **[Tarefa mais urgente]**
   - Arquivo: caminho/arquivo.ext
   - O que fazer: descrição específica
   - Tempo estimado: X horas
   - Bloqueadores: nenhum / [lista]

2. **[Segunda tarefa]**
   - ...

[Continue até listar TODAS as tarefas pendentes]
```

---

## ETAPA 4 - GERAR RELATÓRIO

**Crie tabela markdown completa:**

```markdown
# RELATÓRIO DE AUDITORIA - [DATA]

## Resumo Executivo

- ✅ Componentes funcionais: X
- 🚧 Em desenvolvimento: Y
- ❌ Quebrados/Faltando: Z
- 📝 Total de arquivos analisados: W

## Detalhamento por Área

### Backend PHP

| Arquivo | Status | Funções principais | Observações |
|---------|--------|-------------------|-------------|
| RagController.php | ✅/🚧/❌ | upload(), ingest() | [comentário] |
| RagAnswerController.php | ✅/🚧/❌ | answer(), query() | [comentário] |
| ... | ... | ... | ... |

### Python - Extração

| Arquivo | Status | Formatos suportados | Observações |
|---------|--------|---------------------|-------------|
| main_extractor.py | ✅/🚧/❌ | PDF, DOCX, etc | [comentário] |
| extract.py | ✅/🚧/❌ | PDF | [comentário] |
| ... | ... | ... | ... |

### Python - RAG Search

| Arquivo | Status | Funcionalidade | Observações |
|---------|--------|----------------|-------------|
| rag_search.py | ✅/🚧/❌ | CLI principal | [comentário] |
| embeddings_service.py | ✅/🚧/❌ | Gera embeddings | [comentário] |
| vector_search.py | ✅/🚧/❌ | Busca vetorial | [comentário] |
| llm_service.py | ✅/🚧/❌ | Gemini/OpenAI | [comentário] |
| ... | ... | ... | ... |

### Frontend

| Pasta/Arquivo | Status | Tecnologia | Integrado com backend? |
|---------------|--------|------------|----------------------|
| public/front/ | ✅/🚧/❌ | HTML/JS/CSS | Sim/Não/Parcial |
| public/rag-frontend/ | ✅/🚧/❌ | React/Vue/etc | Sim/Não/Parcial |

### Banco de Dados

| Tabela | Existe? | Campos principais | Índices | Observações |
|--------|---------|-------------------|---------|-------------|
| documents | ✅/❌ | id, title, source | ... | [comentário] |
| chunks | ✅/❌ | id, content, embedding | ... | [comentário] |
| users | ✅/❌ | ... | ... | [comentário] |

### Integrações

| Tipo | De → Para | Status | Arquivo | Observações |
|------|-----------|--------|---------|-------------|
| PHP → Python | RagController → extract.py | ✅/🚧/❌ | linha X | [comentário] |
| PHP → Python | ? → rag_search.py | ✅/🚧/❌ | linha X | [comentário] |
| Frontend → PHP | ? → /api/... | ✅/🚧/❌ | arquivo.js | [comentário] |

## Problemas Críticos Encontrados

1. **[Problema 1]**
   - Arquivo: caminho/arquivo.ext
   - Linha: X
   - Descrição: [detalhes]
   - Impacto: Alto/Médio/Baixo
   - Solução sugerida: [descrição]

2. **[Problema 2]**
   - ...

## Tarefas Prioritárias (Top 10)

1. [ ] **[Tarefa]** - Arquivo: X - Tempo: Y - Impacto: Alto
2. [ ] **[Tarefa]** - Arquivo: X - Tempo: Y - Impacto: Alto
3. ...

## Estatísticas de Código

- Total arquivos PHP: X
- Total arquivos Python: Y
- Total linhas PHP: ~Z
- Total linhas Python: ~W
- Cobertura de testes: [se houver]
- Endpoints API: [número]

---

Auditoria realizada em: [DATA E HORA]
Última modificação no código: [GIT LAST COMMIT DATE]
```

---

## REGRAS CRÍTICAS PARA EXECUÇÃO

### Você DEVE:
1. ✅ **Ler TODOS os arquivos mencionados** (não assumir nada)
2. ✅ **Verificar conteúdo real** (não usar conhecimento prévio desatualizado)
3. ✅ **Ser específico** (não dizer "parece funcionar" - dizer "função X está implementada e retorna Y")
4. ✅ **Marcar ✅ APENAS se código está completo** (sem TODOs, sem stubs vazios)
5. ✅ **Preservar formatação ASCII** nos arquivos .cursorrules e README
6. ✅ **Adicionar data** em TODAS as seções atualizadas
7. ✅ **Manter estrutura** dos arquivos originais (não reescrever do zero)

### Você NÃO DEVE:
1. ❌ **Inventar status** (sempre baseie em código real)
2. ❌ **Assumir que algo funciona** sem verificar
3. ❌ **Remover seções importantes** dos arquivos (STACK, ARQUITETURA, etc)
4. ❌ **Marcar ✅ se há dúvidas** (use 🚧 quando incerto)
5. ❌ **Simplificar demais** (preferir detalhes a resumos vagos)

---

## FORMATO DE RESPOSTA

Após executar todas as etapas, responda assim:

```
ATUALIZAÇÃO DE ESTADO CONCLUÍDA ✅

📊 RESUMO:
- Arquivos analisados: X
- ✅ Funcionais: Y
- 🚧 Parciais: Z
- ❌ Quebrados: W

📝 ARQUIVOS ATUALIZADOS:
✓ .cursorrules
✓ PROJECT_README.md

📋 RELATÓRIO COMPLETO:
[Cole a tabela markdown aqui]

🎯 TOP 3 PRIORIDADES:
1. [Tarefa]
2. [Tarefa]
3. [Tarefa]

⚠️ PROBLEMAS CRÍTICOS:
[Se houver, liste aqui]

---
Próxima ação sugerida: [o que fazer agora]
```

---

## EXECUTAR AGORA

Cursor, execute este prompt completamente:
1. Faça auditoria completa do código em disco
2. Atualize .cursorrules
3. Atualize PROJECT_README.md
4. Gere relatório completo
5. Apresente resultado no formato especificado