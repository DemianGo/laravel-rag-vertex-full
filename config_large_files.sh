#!/bin/bash
# Configuration script for large file support (up to 5000 pages)
# Run with: sudo bash config_large_files.sh

echo "═══════════════════════════════════════════════════════════════════════"
echo "🔧 Configurando suporte para arquivos grandes (até 5.000 páginas)"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""

# Detect PHP-FPM version
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PHP_FPM_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
PHP_CLI_INI="/etc/php/${PHP_VERSION}/cli/php.ini"
PHP_FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

echo "📌 PHP Version: ${PHP_VERSION}"
echo ""

# 1. Update PHP-FPM php.ini
echo "1️⃣ Atualizando PHP-FPM php.ini..."
if [ -f "$PHP_FPM_INI" ]; then
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 512M/' "$PHP_FPM_INI"
    sed -i 's/^post_max_size = .*/post_max_size = 512M/' "$PHP_FPM_INI"
    sed -i 's/^memory_limit = .*/memory_limit = 2G/' "$PHP_FPM_INI"
    sed -i 's/^max_execution_time = .*/max_execution_time = 300/' "$PHP_FPM_INI"
    sed -i 's/^max_input_time = .*/max_input_time = 300/' "$PHP_FPM_INI"
    
    # Add if not exists
    grep -q "^upload_max_filesize" "$PHP_FPM_INI" || echo "upload_max_filesize = 512M" >> "$PHP_FPM_INI"
    grep -q "^post_max_size" "$PHP_FPM_INI" || echo "post_max_size = 512M" >> "$PHP_FPM_INI"
    grep -q "^memory_limit" "$PHP_FPM_INI" || echo "memory_limit = 2G" >> "$PHP_FPM_INI"
    grep -q "^max_execution_time" "$PHP_FPM_INI" || echo "max_execution_time = 300" >> "$PHP_FPM_INI"
    grep -q "^max_input_time" "$PHP_FPM_INI" || echo "max_input_time = 300" >> "$PHP_FPM_INI"
    
    echo "   ✅ PHP-FPM php.ini atualizado: $PHP_FPM_INI"
else
    echo "   ⚠️  PHP-FPM php.ini não encontrado: $PHP_FPM_INI"
fi
echo ""

# 2. Update PHP CLI php.ini (for artisan commands)
echo "2️⃣ Atualizando PHP CLI php.ini..."
if [ -f "$PHP_CLI_INI" ]; then
    sed -i 's/^memory_limit = .*/memory_limit = 2G/' "$PHP_CLI_INI"
    sed -i 's/^max_execution_time = .*/max_execution_time = 300/' "$PHP_CLI_INI"
    
    grep -q "^memory_limit" "$PHP_CLI_INI" || echo "memory_limit = 2G" >> "$PHP_CLI_INI"
    grep -q "^max_execution_time" "$PHP_CLI_INI" || echo "max_execution_time = 300" >> "$PHP_CLI_INI"
    
    echo "   ✅ PHP CLI php.ini atualizado: $PHP_CLI_INI"
else
    echo "   ⚠️  PHP CLI php.ini não encontrado: $PHP_CLI_INI"
fi
echo ""

# 3. Update PHP-FPM pool configuration
echo "3️⃣ Atualizando PHP-FPM pool (workers)..."
if [ -f "$PHP_FPM_POOL" ]; then
    # Backup original
    cp "$PHP_FPM_POOL" "$PHP_FPM_POOL.bak.$(date +%Y%m%d_%H%M%S)"
    
    # Update or add pool settings
    sed -i 's/^pm.max_children = .*/pm.max_children = 10/' "$PHP_FPM_POOL"
    sed -i 's/^pm.start_servers = .*/pm.start_servers = 3/' "$PHP_FPM_POOL"
    sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$PHP_FPM_POOL"
    sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 5/' "$PHP_FPM_POOL"
    sed -i 's/^pm.max_requests = .*/pm.max_requests = 500/' "$PHP_FPM_POOL"
    
    echo "   ✅ PHP-FPM pool atualizado: $PHP_FPM_POOL"
else
    echo "   ⚠️  PHP-FPM pool não encontrado: $PHP_FPM_POOL"
fi
echo ""

# 4. Update NGINX configuration
echo "4️⃣ Atualizando NGINX..."
NGINX_SITE="/etc/nginx/sites-available/default"
NGINX_CONF="/etc/nginx/nginx.conf"

if [ -f "$NGINX_SITE" ]; then
    # Backup
    cp "$NGINX_SITE" "$NGINX_SITE.bak.$(date +%Y%m%d_%H%M%S)"
    
    # Remove existing client_max_body_size if exists
    sed -i '/client_max_body_size/d' "$NGINX_SITE"
    
    # Add new settings in server block
    sed -i '/server {/a \    client_max_body_size 512M;\n    client_body_timeout 300s;\n    fastcgi_read_timeout 300s;\n    fastcgi_send_timeout 300s;' "$NGINX_SITE"
    
    echo "   ✅ NGINX site config atualizado: $NGINX_SITE"
elif [ -f "$NGINX_CONF" ]; then
    cp "$NGINX_CONF" "$NGINX_CONF.bak.$(date +%Y%m%d_%H%M%S)"
    
    # Add to http block if not exists
    grep -q "client_max_body_size" "$NGINX_CONF" || sed -i '/http {/a \    client_max_body_size 512M;\n    client_body_timeout 300s;' "$NGINX_CONF"
    
    echo "   ✅ NGINX global config atualizado: $NGINX_CONF"
else
    echo "   ⚠️  NGINX config não encontrado"
fi
echo ""

# 5. Restart services
echo "5️⃣ Reiniciando serviços..."
systemctl restart php${PHP_VERSION}-fpm && echo "   ✅ PHP-FPM reiniciado" || echo "   ⚠️  Erro ao reiniciar PHP-FPM"
systemctl restart nginx && echo "   ✅ NGINX reiniciado" || echo "   ⚠️  Erro ao reiniciar NGINX"
echo ""

# 6. Verify configuration
echo "═══════════════════════════════════════════════════════════════════════"
echo "✅ Verificação das configurações:"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""

echo "📊 PHP-FPM Configuration:"
php-fpm${PHP_VERSION} -i | grep -E "upload_max_filesize|post_max_size|memory_limit|max_execution_time" 2>/dev/null || echo "   (use: php -i para ver todas as configurações)"
echo ""

echo "📊 PHP CLI Configuration:"
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
php -r "echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"
echo ""

echo "═══════════════════════════════════════════════════════════════════════"
echo "✅ Configuração concluída!"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""
echo "📝 Próximos passos:"
echo "   1. Verifique se os valores acima estão corretos (512M, 2G, 300s)"
echo "   2. Teste upload de arquivo grande no frontend"
echo "   3. Monitore logs: tail -f storage/logs/laravel.log"
echo ""

