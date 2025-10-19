# RAG MAINTENANCE

## Manutenção do Sistema RAG

### Rotinas de Manutenção

#### 1. Limpeza de Cache
```bash
# Limpar cache de modelos
python3 scripts/rag_search/clear_model_cache.py

# Limpar cache do Laravel
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

#### 2. Verificação de Saúde
```bash
# Verificar saúde do sistema
curl -X GET http://localhost:8000/api/rag/python-health

# Verificar embeddings
python3 scripts/rag_search/rag_search.py --query "teste" --document-id 1
```

#### 3. Backup de Dados
```bash
# Backup do banco de dados
pg_dump laravel_rag_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup de documentos
tar -czf documents_backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/documents/
```

#### 4. Monitoramento de Performance
- Verificar logs de erro
- Monitorar uso de memória
- Verificar tempo de resposta
- Analisar métricas de cache

### Troubleshooting

#### Problemas Comuns

1. **Timeout em uploads grandes**
   - Solução: Aumentar timeout no PHP
   - Verificar: Tamanho do arquivo vs timeout

2. **OCR não funcionando**
   - Solução: Verificar Google Vision API
   - Verificar: Credenciais do Google Cloud

3. **Busca lenta**
   - Solução: Verificar cache de embeddings
   - Verificar: Índices do banco de dados

4. **Erro de memória**
   - Solução: Aumentar memory_limit
   - Verificar: Tamanho dos arquivos

### Logs Importantes

- `storage/logs/laravel.log` - Logs do Laravel
- `scripts/rag_search/audit_logs/` - Logs de auditoria
- `public/logs/` - Logs de performance

### Comandos Úteis

```bash
# Reiniciar serviços
sudo systemctl restart nginx
sudo systemctl restart postgresql

# Verificar status
systemctl status nginx
systemctl status postgresql

# Verificar logs
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/error.log
```

### Manutenção Preventiva

1. **Diária**
   - Verificar logs de erro
   - Monitorar uso de recursos

2. **Semanal**
   - Limpar cache antigo
   - Verificar backups

3. **Mensal**
   - Atualizar dependências
   - Revisar configurações
   - Otimizar banco de dados
