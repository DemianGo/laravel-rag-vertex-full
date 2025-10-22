#!/bin/bash

# Script para configurar Google Vision OCR no .env
echo "==> Configurando Google Vision OCR no .env..."

# Verificar se o arquivo .env existe
if [ ! -f .env ]; then
    echo "❌ Arquivo .env não encontrado!"
    exit 1
fi

# Backup do .env atual
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Adicionar configurações do Google Vision OCR
echo "" >> .env
echo "# Google Cloud Vision OCR Configuration" >> .env
echo "GOOGLE_APPLICATION_CREDENTIALS=/home/freeb/.config/gcloud/application_default_credentials.json" >> .env
echo "GOOGLE_CLOUD_PROJECT=liberai-ai" >> .env

echo "✅ Configurações do Google Vision OCR adicionadas ao .env"
echo "📋 Configurações adicionadas:"
echo "   - GOOGLE_APPLICATION_CREDENTIALS=/home/freeb/.config/gcloud/application_default_credentials.json"
echo "   - GOOGLE_CLOUD_PROJECT=liberai-ai"
echo ""
echo "🔄 Execute o dev-start.sh para aplicar as configurações"
