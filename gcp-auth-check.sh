#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")"

QUIET=0
if [ "${1:-}" = "--quiet" ]; then QUIET=1; shift || true; fi

read_env() {
  local key="$1"; local def="$2"; local v
  v="$(sed -nE "s/^${key}=(.*)/\1/p" .env 2>/dev/null | tail -n1 | tr -d '"' | tr -d "'")" || true
  [ -n "${v:-}" ] && echo "$v" || echo "$def"
}

PROJECT_ID="$(read_env GCP_PROJECT_ID liberai-ai)"
LOCATION="$(read_env GCP_EMBEDDING_LOCATION us-central1)"
MODEL="$(read_env GCP_EMBEDDING_MODEL gemini-embedding-001)"

[ "$QUIET" -eq 1 ] || gcloud config list account --format='text(core.account)'
[ "$QUIET" -eq 1 ] || gcloud config list project --format='text(core.project)'

TOKEN="$(gcloud auth application-default print-access-token || true)"
if [ -z "$TOKEN" ]; then
  [ "$QUIET" -eq 1 ] || echo "[ERRO] Sem token ADC."
  exit 2
fi

URL="https://${LOCATION}-aiplatform.googleapis.com/v1/projects/${PROJECT_ID}/locations/${LOCATION}/publishers/google/models/${MODEL}:predict"
HTTP_CODE=$(curl -sS -o /tmp/vertex-check.json -w "%{http_code}" \
  -H "Authorization: Bearer ${TOKEN}" -H "Content-Type: application/json" \
  -X POST "$URL" \
  -d '{"instances":[{"content":"ping","task_type":"RETRIEVAL_DOCUMENT"}],"parameters":{"outputDimensionality":768}}' || echo "000")

if [ "$HTTP_CODE" != "200" ]; then
  [ "$QUIET" -eq 1 ] || { echo "[ERRO] Vertex falhou (HTTP $HTTP_CODE):"; cat /tmp/vertex-check.json || true; }
  exit 3
fi

[ "$QUIET" -eq 1 ] || echo "[OK] Vertex embeddings respondendo."
