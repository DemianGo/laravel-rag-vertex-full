#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")"

read_env() {
  local key="$1"; local def="$2"; local v
  v="$(sed -nE "s/^${key}=(.*)/\1/p" .env 2>/dev/null | tail -n1 | tr -d '"' | tr -d "'")" || true
  [ -n "${v:-}" ] && echo "$v" || echo "$def"
}
PROJECT_ID="${1:-"$(read_env GCP_PROJECT_ID liberai-ai)"}"

echo "[i] Projeto alvo: ${PROJECT_ID}"
echo "[1/5] Revogando ADC antiga (se existir)..."
gcloud auth application-default revoke -q || true
echo "[2/5] Limpando arquivos ADC antigos..."
rm -f ~/.config/gcloud/application_default_credentials.json 2>/dev/null || true
rm -f ~/.config/gcloud/legacy_credentials/*/adc.json 2>/dev/null || true
echo "[3/5] Login ADC (abra a URL, autorize e cole o código aqui)..."
gcloud auth application-default login --no-launch-browser --scopes=https://www.googleapis.com/auth/cloud-platform
echo "[4/5] Ajustando projeto..."
gcloud config set project "${PROJECT_ID}" -q
gcloud auth application-default set-quota-project "${PROJECT_ID}" -q
echo "[5/5] Validando token..."
TOKEN="$(gcloud auth application-default print-access-token || true)"
[ -z "$TOKEN" ] && { echo "[ERRO] Sem token ADC. Tente novamente."; exit 1; }
echo "[OK] ADC configurado para o projeto: ${PROJECT_ID}"
