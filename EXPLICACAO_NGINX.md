# Por que Nginx não foi usado desde o início?

## ❌ RAZÃO PRINCIPAL: Laravel foi usado como servidor web

Projeto original:
- **Laravel (`php artisan serve`)** na porta 8000
- **FastAPI** na porta 8002 (só para APIs)
- **Sem Nginx** em desenvolvimento
- **Sem Nginx** em produção

## 📝 EVIDÊNCIAS

### 1. Script `dev-start.sh` linha 1-15:
```bash
php artisan serve  # Laravel servindo TUDO
```

### 2. Script `scripts/dev-up.sh` linha 14:
```bash
nohup php artisan serve --host=127.0.0.1 --port=${PORT} > /tmp/laravel-serve.log 2>&1 &
```

### 3. `.cursorrules` linha 3-9:
```
LARAVEL APENAS PARA:
• Mostrar views (HTML/CSS/JS)   ← LARAVEL servindo HTML
• Login e Registro
• Admin Panel
```

### 4. `docker-compose.yml` linha 76-89:
```yaml
nginx:
  profiles:
    - production  # ← NGINX SÓ EM PRODUÇÃO E OPCIONAL!
```

## 🎯 CONCLUSÃO

**Por que não usaram Nginx?**
1. Laravel **resolve** views HTML/CSS/JS
2. `php artisan serve` é rápido para desenvolvimento
3. Nginx é opcional (profile: production)
4. Sem deployment profissional de produção

**Por que precisa de Nginx agora?**
1. Eliminando Laravel
2. FastAPI não serve HTML nativamente
3. Nginx é o padrão para produção
4. Docker Compose já tem Nginx configurado

---
**SOLUÇÃO IMEDIATA SEM PROGRAMAR:**
1. ✅ Usar FastAPI StaticFiles (2 linhas)
2. ✅ OU usar nginx já no docker-compose.yml

---
**Data**: 2025-11-01
