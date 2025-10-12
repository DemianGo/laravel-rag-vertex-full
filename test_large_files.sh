#!/bin/bash
# Automated testing script for large file support
# Tests uploads and RAG searches without hardcoded file-specific logic

echo "═══════════════════════════════════════════════════════════════════════"
echo "🧪 TESTE AUTOMATIZADO - ARQUIVOS GIGANTES"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""

BASE_URL="http://localhost:8000"
API_ENDPOINT="${BASE_URL}/api/rag/ingest"
TEST_FILES_DIR="/tmp/large_test_files"
RESULTS_FILE="test_results_$(date +%Y%m%d_%H%M%S).log"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

log_test() {
    echo "$1" | tee -a "$RESULTS_FILE"
}

test_upload() {
    local file=$1
    local filename=$(basename "$file")
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    log_test ""
    log_test "───────────────────────────────────────────────────────────────────────"
    log_test "📤 Teste ${TOTAL_TESTS}: Upload ${filename}"
    log_test "   Tamanho: $(du -h "$file" | cut -f1)"
    
    # Upload file
    response=$(curl -s -w "\n%{http_code}" -X POST "$API_ENDPOINT" \
        -F "document=@${file}" \
        -F "user_id=1" \
        -F "title=Test Large File - ${filename}" \
        2>/dev/null)
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n -1)
    
    # Check response
    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        # Extract document_id from response
        doc_id=$(echo "$body" | grep -o '"document_id":[0-9]*' | grep -o '[0-9]*')
        chunks=$(echo "$body" | grep -o '"total_chunks":[0-9]*' | grep -o '[0-9]*')
        
        if [ -n "$doc_id" ] && [ "$doc_id" -gt 0 ]; then
            PASSED_TESTS=$((PASSED_TESTS + 1))
            echo -e "${GREEN}   ✅ PASSOU${NC}" | tee -a "$RESULTS_FILE"
            log_test "   Document ID: $doc_id"
            log_test "   Chunks: $chunks"
            
            # Store for later RAG test
            echo "${doc_id}|${filename}" >> "/tmp/uploaded_docs.txt"
        else
            FAILED_TESTS=$((FAILED_TESTS + 1))
            echo -e "${RED}   ❌ FALHOU${NC} - No document_id" | tee -a "$RESULTS_FILE"
            log_test "   Response: $body"
        fi
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}   ❌ FALHOU${NC} - HTTP $http_code" | tee -a "$RESULTS_FILE"
        log_test "   Response: $body"
    fi
}

test_rag_search() {
    local doc_id=$1
    local filename=$2
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    log_test ""
    log_test "───────────────────────────────────────────────────────────────────────"
    log_test "🔍 Teste ${TOTAL_TESTS}: Busca RAG - ${filename}"
    
    # Generic query that should work for any document type
    query="O que este documento contém? Faça um resumo breve."
    
    response=$(curl -s -w "\n%{http_code}" -X POST "${BASE_URL}/api/rag/python-search" \
        -H "Content-Type: application/json" \
        -d "{\"query\":\"$query\",\"document_id\":$doc_id,\"smart_mode\":true}" \
        2>/dev/null)
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n -1)
    
    if [ "$http_code" = "200" ]; then
        # Check if response contains actual answer
        answer=$(echo "$body" | grep -o '"answer":"[^"]*"' | head -n1)
        
        if [ -n "$answer" ] && [ "$answer" != '"answer":""' ]; then
            PASSED_TESTS=$((PASSED_TESTS + 1))
            echo -e "${GREEN}   ✅ PASSOU${NC}" | tee -a "$RESULTS_FILE"
            
            # Extract answer length (generic indicator of quality)
            answer_length=$(echo "$answer" | wc -c)
            log_test "   Resposta: ${answer_length} caracteres"
        else
            FAILED_TESTS=$((FAILED_TESTS + 1))
            echo -e "${RED}   ❌ FALHOU${NC} - Empty answer" | tee -a "$RESULTS_FILE"
        fi
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}   ❌ FALHOU${NC} - HTTP $http_code" | tee -a "$RESULTS_FILE"
        log_test "   Response: $body"
    fi
}

# Main test execution
echo "🚀 Iniciando testes..." | tee "$RESULTS_FILE"
echo "" | tee -a "$RESULTS_FILE"

# Clear previous uploaded docs list
rm -f /tmp/uploaded_docs.txt

# Phase 1: Upload Tests
log_test "═══════════════════════════════════════════════════════════════════════"
log_test "FASE 1: TESTES DE UPLOAD"
log_test "═══════════════════════════════════════════════════════════════════════"

if [ -d "$TEST_FILES_DIR" ]; then
    for file in "$TEST_FILES_DIR"/*; do
        if [ -f "$file" ]; then
            test_upload "$file"
        fi
    done
else
    echo -e "${YELLOW}⚠️  Diretório de testes não encontrado: $TEST_FILES_DIR${NC}"
    echo "   Execute antes: python3 generate_large_test_files.py"
    exit 1
fi

# Phase 2: RAG Search Tests
log_test ""
log_test "═══════════════════════════════════════════════════════════════════════"
log_test "FASE 2: TESTES DE BUSCA RAG"
log_test "═══════════════════════════════════════════════════════════════════════"

if [ -f "/tmp/uploaded_docs.txt" ]; then
    # Test RAG on 3 random uploaded documents (to save time)
    shuf -n 3 /tmp/uploaded_docs.txt | while IFS='|' read -r doc_id filename; do
        test_rag_search "$doc_id" "$filename"
    done
else
    log_test "⚠️  Nenhum documento foi uploadado com sucesso. Pulando testes de RAG."
fi

# Final Report
log_test ""
log_test "═══════════════════════════════════════════════════════════════════════"
log_test "📊 RELATÓRIO FINAL"
log_test "═══════════════════════════════════════════════════════════════════════"
log_test "Total de testes: $TOTAL_TESTS"
log_test "Passou: $PASSED_TESTS"
log_test "Falhou: $FAILED_TESTS"

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✅ TODOS OS TESTES PASSARAM!${NC}" | tee -a "$RESULTS_FILE"
    EXIT_CODE=0
else
    echo -e "${RED}❌ $FAILED_TESTS TESTE(S) FALHARAM${NC}" | tee -a "$RESULTS_FILE"
    EXIT_CODE=1
fi

log_test ""
log_test "📝 Log completo salvo em: $RESULTS_FILE"
log_test ""

exit $EXIT_CODE

