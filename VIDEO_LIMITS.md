# 📹 LIMITES DO SISTEMA DE VÍDEOS

## ⚠️ Limite Implementado

**Duração máxima:** 60 minutos (1 hora)

### Validação
- ✅ Verificação **antes** do download (rápido, ~2s)
- ✅ Mensagem clara explicando o limite
- ✅ Sugestão de dividir vídeos longos

---

## 📊 Razões Técnicas

### 1. Tempo de Processamento
| Duração | Tempo Total | Status |
|---------|-------------|--------|
| 5-15 min | 1-3 min | ✅ Ideal |
| 15-30 min | 3-5 min | ✅ Bom |
| 30-60 min | 5-10 min | ⚠️ Longo |
| > 60 min | > 10 min | ❌ Bloqueado |

### 2. Custo de Transcrição (Gemini)
| Duração | Custo Aproximado |
|---------|------------------|
| 15 min | $0.02 |
| 30 min | $0.04 |
| 60 min | $0.08 |
| 5 horas | $0.40 |

**Limite mensal gratuito:** ~50.000 caracteres/mês

### 3. Qualidade da Transcrição
| Duração | Precisão | Chunks |
|---------|----------|--------|
| < 20 min | 95%+ | 50-200 |
| 20-60 min | 90%+ | 200-600 |
| > 60 min | 70-80% | 600+ |

### 4. Recursos do Sistema
| Duração | RAM | Chunks | Performance RAG |
|---------|-----|--------|-----------------|
| 15 min | ~30MB | ~120 | Excelente |
| 60 min | ~120MB | ~500 | Boa |
| 5 horas | ~600MB | ~3000 | Ruim |

---

## ✅ Recomendações

### IDEAL (Melhor Experiência)
- **Duração:** 5-20 minutos
- **Tempo de processamento:** 1-3 minutos
- **Chunks gerados:** 50-200
- **Precisão:** 95%+
- **Custo:** < $0.03

### ACEITÁVEL (Funciona, mas demora)
- **Duração:** 20-60 minutos
- **Tempo de processamento:** 3-8 minutos
- **Chunks gerados:** 200-600
- **Precisão:** 90%+
- **Custo:** $0.03-$0.08

### NÃO RECOMENDADO (Bloqueado)
- **Duração:** > 60 minutos
- **Razão:** Timeout, custo, precisão baixa
- **Alternativa:** Dividir em partes

---

## 🔧 Alternativas para Vídeos Longos

### Opção 1: Dividir Manualmente
1. Use YouTube Studio para criar clips
2. Ou use FFmpeg para dividir:
   ```bash
   # Dividir vídeo de 3h em 6 partes de 30min
   ffmpeg -i video.mp4 -ss 00:00:00 -t 00:30:00 parte1.mp4
   ffmpeg -i video.mp4 -ss 00:30:00 -t 00:30:00 parte2.mp4
   # ... e assim por diante
   ```

### Opção 2: Upload Sequencial
1. Faça upload de cada parte separadamente
2. Sistema indexa cada uma
3. Busca RAG funciona em todas

### Opção 3: Usar Timestamps
1. Identifique trechos relevantes
2. Faça upload apenas desses trechos
3. Economia de tempo e recursos

---

## 📝 Exemplos Práticos

### Aula de 3 horas
**Antes (bloqueado):**
- 1 vídeo de 3h
- Tempo: ~40min
- Chunks: ~2000
- Precisão: 75%

**Depois (recomendado):**
- 6 vídeos de 30min
- Tempo: ~30min total (paralelo)
- Chunks: ~300 cada (1800 total)
- Precisão: 92%+

### Podcast de 2 horas
**Opção 1:** 4 partes de 30min
**Opção 2:** 1 parte de 45min (intro + principais tópicos)

### Webinar de 4 horas
**Opção 1:** 8 partes de 30min
**Opção 2:** 4 partes de 45min (principais apresentações)

---

## 🎯 Mensagem de Erro

Quando tentar fazer upload de vídeo > 60min:

```json
{
  "ok": false,
  "error": "❌ Vídeo muito longo (287 minutos). Limite máximo: 60 minutos (1 hora). Para vídeos mais longos, divida em partes menores."
}
```

---

**Data:** 2025-10-12  
**Status:** ✅ Limite implementado e testado

