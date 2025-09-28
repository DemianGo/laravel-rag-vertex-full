# RAG SYSTEM MAINTENANCE

## Comandos de Monitoramento

### Health Check Manual
```bash
# Verificação básica de saúde
php artisan rag:monitor

# Verificação com limpeza automática se falhar
php artisan rag:monitor --clear-on-fail
```

### Reset de Emergência
```bash
# Reset completo do sistema RAG (com confirmação)
php artisan rag:reset-system

# Reset forçado (sem confirmação)
php artisan rag:reset-system --force
```

## Schedulers Automáticos

### Configurado no app/Console/Kernel.php:

1. **Limpeza diária de cache**: Todo dia às 3:00 AM
2. **Health check com limpeza**: A cada hora
3. **Health check básico**: A cada 15 minutos

### Para ativar os schedulers:

```bash
# Adicionar ao crontab do sistema
* * * * * cd /var/www/html/laravel-rag-vertex-full && php artisan schedule:run >> /dev/null 2>&1
```

## Monitoramento de Logs

### Localização dos logs:
- Laravel: `/var/www/html/laravel-rag-vertex-full/storage/logs/laravel.log`
- RAG Health: Buscar por "RAG Health Check" nos logs

### Comandos úteis para monitoramento:
```bash
# Ver logs em tempo real
tail -f storage/logs/laravel.log

# Filtrar apenas logs RAG
tail -f storage/logs/laravel.log | grep "RAG"

# Ver últimas 50 linhas de logs RAG
grep "RAG" storage/logs/laravel.log | tail -50
```

## Prevenção de Cache Corrompido

### Sinais de cache corrompido:
- Chat não retorna resultados
- APIs demoram muito para responder
- Resultados inconsistentes ou vazios

### Soluções automáticas implementadas:
1. **Health checks regulares** (15 min / 1 hora)
2. **Limpeza automática** quando detecta problemas
3. **Reset de emergência** disponível
4. **Limpeza diária preventiva** (3:00 AM)

### Comandos de emergência manual:
```bash
# Sequência completa de correção
php artisan rag:reset-system --force

# Apenas limpeza de cache RAG
curl -X POST http://127.0.0.1:8000/api/rag/cache/clear

# Apenas limpeza Laravel
php artisan cache:clear && php artisan config:clear
```

## Alertas e Notificações

### Logs estruturados para monitoramento:
- `RAG Health Check: HEALTHY` - Sistema funcionando
- `RAG Health Check: UNHEALTHY` - Sistema com problemas
- `RAG Cache Cleared` - Cache foi limpo automaticamente
- `RAG System Reset Performed` - Reset manual executado

### Integração com ferramentas de monitoramento:
- Logs em formato JSON estruturado
- Métricas de response time
- Timestamps para análise temporal

## Troubleshooting

### Problemas comuns:

1. **Chat não funciona**:
   ```bash
   php artisan rag:monitor --clear-on-fail
   ```

2. **APIs lentas**:
   ```bash
   php artisan rag:reset-system --force
   ```

3. **Resultados vazios sempre**:
   ```bash
   # Verificar se backend está rodando
   curl http://127.0.0.1:8000/api/health

   # Se não responder, reiniciar backend
   # Se responder, fazer reset
   php artisan rag:reset-system --force
   ```

4. **Scheduler não roda**:
   ```bash
   # Verificar crontab
   crontab -l

   # Testar manualmente
   php artisan schedule:run
   ```

## Métricas de Performance

### Benchmarks normais:
- Health check: < 200ms
- Cache clear: < 5s
- System reset: < 20s
- Query response: < 2s

### Alertas (implementar monitoring):
- Response time > 5s
- Health check falha 3x seguidas
- Cache clear falha
- Mais de 3 resets por dia