#!/usr/bin/env bash
set -euo pipefail

BASE="${1:-http://127.0.0.1:8000}"

pass() { echo -e "✅ $1"; }
fail() { echo -e "❌ $1"; }

check_json () {
  # $1 = resposta com headers (-i)
  local ct
  ct="$(printf "%s" "$1" | awk 'BEGIN{IGNORECASE=1}/^Content-Type:/{print $0}' | tr -d "\r")"
  if echo "$ct" | grep -qi "application/json"; then
    pass "Content-Type é JSON: ${ct}"
    return 0
  else
    fail "Content-Type NÃO é JSON: ${ct}"
    return 1
  fi
}

echo "== Smoke: $BASE =="

echo "--- /rag/debug/echo"
RESP=$(curl -s -i "$BASE/rag/debug/echo")
check_json "$RESP" || true
printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | head -c 300; echo; echo

echo "--- /rag/ingest (JSON)"
BODY='{"title":"smoke-doc","text":"linha A\nlinha B\nlinha C"}'
RESP=$(curl -s -i -X POST "$BASE/rag/ingest" -H "Content-Type: application/json" --data-binary "$BODY")
check_json "$RESP" || true
printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | head -c 500; echo; echo

DOCID=$(printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | jq -r '.document_id // empty' 2>/dev/null || true)
if [ -n "${DOCID:-}" ]; then pass "document_id = $DOCID"; else fail "não achei document_id na resposta de ingest"; fi

echo "--- /docs/list"
RESP=$(curl -s -i "$BASE/rag/docs/list")
check_json "$RESP" || true
printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | head -c 500; echo; echo

echo "--- /docs/preview"
RESP=$(curl -s -i "$BASE/rag/docs/preview")
check_json "$RESP" || true
printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | head -c 500; echo; echo

echo "--- /rag/query (sobre o doc atual)"
RESP=$(curl -s -i "$BASE/rag/query?query=do%20que%20se%20trata%20o%20documento%3F&top_k=5")
check_json "$RESP" || true
printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | head -c 800; echo; echo

echo "--- /rag/answer (resumo)"
RESP=$(curl -s -i "$BASE/rag/answer?query=resuma%20o%20documento&top_k=6")
check_json "$RESP" || true
printf "%s" "$RESP" | awk 'BEGIN{p=0}/^\r?$/{p=1;next} p{print}' | head -c 800; echo; echo

echo "== Fim =="
