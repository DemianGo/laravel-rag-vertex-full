# Guia de Deploy - Laravel RAG System

## 🚀 **Deploy em Produção**

### **Pré-requisitos do Servidor**

#### **Sistema Operacional**
- Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- 8GB RAM mínimo (16GB recomendado)
- 100GB SSD mínimo (500GB recomendado)
- CPU: 4 cores mínimo (8 cores recomendado)

#### **Software Necessário**
```bash
# PHP 8.4+
sudo apt update
sudo apt install php8.4 php8.4-fpm php8.4-cli php8.4-mysql php8.4-pgsql php8.4-xml php8.4-gd php8.4-curl php8.4-mbstring php8.4-zip php8.4-bcmath php8.4-intl

# PostgreSQL 14+
sudo apt install postgresql postgresql-contrib

# Python 3.12+
sudo apt install python3.12 python3.12-pip python3.12-venv

# Node.js 18+
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs

# Nginx
sudo apt install nginx

# Redis (opcional, para cache)
sudo apt install redis-server
```

### **1. Configuração do Banco de Dados**

```bash
# Criar usuário e banco
sudo -u postgres psql
CREATE USER laravel_rag WITH PASSWORD 'secure_password';
CREATE DATABASE laravel_rag OWNER laravel_rag;
GRANT ALL PRIVILEGES ON DATABASE laravel_rag TO laravel_rag;
\q
```

### **2. Deploy do Código**

```bash
# Clone do repositório
git clone <your-repository-url> /var/www/laravel-rag
cd /var/www/laravel-rag

# Instalar dependências
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Configurar permissões
sudo chown -R www-data:www-data /var/www/laravel-rag
sudo chmod -R 755 /var/www/laravel-rag
sudo chmod -R 775 /var/www/laravel-rag/storage
sudo chmod -R 775 /var/www/laravel-rag/bootstrap/cache
```

### **3. Configuração do Ambiente**

```bash
# Copiar arquivo de ambiente
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate

# Configurar banco de dados
php artisan migrate --force
php artisan db:seed --force

# Otimizar para produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### **4. Configuração do Python**

```bash
# Criar ambiente virtual
python3.12 -m venv /var/www/laravel-rag/venv
source /var/www/laravel-rag/venv/bin/activate

# Instalar dependências Python
pip install -r scripts/rag_search/requirements.txt
pip install -r scripts/document_extraction/requirements.txt
pip install -r scripts/video_processing/requirements.txt

# Configurar permissões
sudo chown -R www-data:www-data /var/www/laravel-rag/venv
```

### **5. Configuração do Nginx**

```nginx
# /etc/nginx/sites-available/laravel-rag
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/laravel-rag/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Upload de arquivos grandes
    client_max_body_size 500M;
    
    # Timeout para processamento
    fastcgi_read_timeout 300;
    fastcgi_send_timeout 300;
}
```

```bash
# Ativar site
sudo ln -s /etc/nginx/sites-available/laravel-rag /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### **6. Configuração do PHP-FPM**

```ini
# /etc/php/8.4/fpm/pool.d/laravel-rag.conf
[laravel-rag]
user = www-data
group = www-data
listen = /var/run/php/php8.4-fpm-laravel-rag.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; Configurações específicas para RAG
php_admin_value[memory_limit] = 2G
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 500M
php_admin_value[post_max_size] = 500M
```

### **7. Configuração do SSL (Let's Encrypt)**

```bash
# Instalar Certbot
sudo apt install certbot python3-certbot-nginx

# Obter certificado SSL
sudo certbot --nginx -d your-domain.com

# Auto-renovação
sudo crontab -e
# Adicionar: 0 12 * * * /usr/bin/certbot renew --quiet
```

### **8. Configuração de Monitoramento**

#### **Logrotate**
```bash
# /etc/logrotate.d/laravel-rag
/var/www/laravel-rag/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
}
```

#### **Systemd Service**
```ini
# /etc/systemd/system/laravel-rag-queue.service
[Unit]
Description=Laravel RAG Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/laravel-rag/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=/var/www/laravel-rag

[Install]
WantedBy=multi-user.target
```

```bash
# Ativar serviço
sudo systemctl enable laravel-rag-queue.service
sudo systemctl start laravel-rag-queue.service
```

### **9. Configuração de Backup**

```bash
# Script de backup
#!/bin/bash
# /usr/local/bin/backup-laravel-rag.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/laravel-rag"
APP_DIR="/var/www/laravel-rag"

# Criar diretório de backup
mkdir -p $BACKUP_DIR

# Backup do banco de dados
pg_dump laravel_rag > $BACKUP_DIR/db_$DATE.sql

# Backup dos arquivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz $APP_DIR/storage $APP_DIR/public/uploads

# Limpar backups antigos (manter 7 dias)
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

# Crontab para execução diária
# 0 2 * * * /usr/local/bin/backup-laravel-rag.sh
```

### **10. Configuração de Performance**

#### **Redis Cache**
```bash
# Configurar Redis
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Configurar no .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### **Otimizações PHP**
```ini
# /etc/php/8.4/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### **11. Configuração de Segurança**

#### **Firewall**
```bash
# Configurar UFW
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

#### **Fail2Ban**
```bash
# Instalar Fail2Ban
sudo apt install fail2ban

# Configurar para Laravel
# /etc/fail2ban/jail.d/laravel-rag.conf
[laravel-rag]
enabled = true
port = 80,443
filter = laravel-rag
logpath = /var/www/laravel-rag/storage/logs/laravel.log
maxretry = 5
bantime = 3600
```

### **12. Monitoramento e Logs**

#### **Logs do Sistema**
```bash
# Verificar logs do Nginx
sudo tail -f /var/log/nginx/error.log

# Verificar logs do PHP-FPM
sudo tail -f /var/log/php8.4-fpm.log

# Verificar logs da aplicação
tail -f /var/www/laravel-rag/storage/logs/laravel.log
```

#### **Monitoramento de Recursos**
```bash
# Instalar htop para monitoramento
sudo apt install htop

# Verificar uso de memória
free -h

# Verificar uso de disco
df -h

# Verificar processos
ps aux | grep php
```

### **13. Comandos de Manutenção**

```bash
# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Otimizar autoloader
composer dump-autoload --optimize

# Verificar saúde do sistema
php artisan rag:health

# Gerar API keys para usuários
php artisan api-keys:generate --user-id=1

# Limpar cache do Python RAG
python3 /var/www/laravel-rag/scripts/rag_search/cache_layer.py --action clear
```

### **14. Troubleshooting**

#### **Problemas Comuns**

1. **Erro de Permissões**
```bash
sudo chown -R www-data:www-data /var/www/laravel-rag
sudo chmod -R 755 /var/www/laravel-rag
sudo chmod -R 775 /var/www/laravel-rag/storage
```

2. **Erro de Memória**
```bash
# Aumentar memory_limit no php.ini
memory_limit = 2G
```

3. **Timeout de Upload**
```bash
# Aumentar timeouts no nginx e php-fpm
client_max_body_size 500M;
fastcgi_read_timeout 300;
```

4. **Erro de Python**
```bash
# Verificar ambiente virtual
source /var/www/laravel-rag/venv/bin/activate
python --version
pip list
```

### **15. Checklist de Deploy**

- [ ] Servidor configurado com requisitos mínimos
- [ ] Banco de dados PostgreSQL criado
- [ ] Código deployado e permissões configuradas
- [ ] Dependências PHP e Python instaladas
- [ ] Nginx configurado e SSL ativado
- [ ] PHP-FPM otimizado
- [ ] Redis configurado (opcional)
- [ ] Queue workers ativos
- [ ] Backup configurado
- [ ] Monitoramento ativo
- [ ] Firewall configurado
- [ ] Logs funcionando
- [ ] Testes de funcionalidade realizados

---

**Deploy concluído com sucesso!** 🚀

O sistema está pronto para produção com alta disponibilidade, segurança e performance otimizadas.
