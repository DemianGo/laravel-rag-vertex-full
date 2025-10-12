#!/bin/bash
# Comprehensive test of all supported formats with 5000 pages
# Tests API bulk-ingest and validates responses

echo "═══════════════════════════════════════════════════════════════════════"
echo "🧪 TESTE COMPLETO - TODOS OS FORMATOS (5000 PÁGINAS)"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""

BASE_URL="http://localhost:8000"
TEST_DIR="/tmp/large_test_files"
RESULTS_LOG="test_all_formats_$(date +%Y%m%d_%H%M%S).log"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
TOTAL_TESTS=0
PASSED=0
FAILED=0

log() {
    echo "$1" | tee -a "$RESULTS_LOG"
}

test_format() {
    local file=$1
    local format=$2
    local expected_pages=$3
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    log ""
    log "───────────────────────────────────────────────────────────────────────"
    log "📝 Teste #${TOTAL_TESTS}: ${format} (${expected_pages} páginas esperadas)"
    log "   Arquivo: $(basename "$file")"
    log "   Tamanho: $(du -h "$file" | cut -f1)"
    
    # Test single file upload
    response=$(curl -s -X POST "${BASE_URL}/api/rag/ingest" \
        -F "document=@${file}" \
        -F "user_id=1" \
        -F "title=Test ${format} 5K pages" \
        2>&1)
    
    # Check response
    if echo "$response" | grep -q '"ok":true\|"success":true'; then
        doc_id=$(echo "$response" | grep -o '"document_id":[0-9]*' | grep -o '[0-9]*' | head -1)
        chunks=$(echo "$response" | grep -o '"chunks_created":[0-9]*' | grep -o '[0-9]*' | head -1)
        
        if [ -n "$doc_id" ] && [ "$doc_id" -gt 0 ]; then
            PASSED=$((PASSED + 1))
            echo -e "${GREEN}   ✅ PASSOU${NC}" | tee -a "$RESULTS_LOG"
            log "   Document ID: $doc_id"
            log "   Chunks criados: $chunks"
            
            # Store for later RAG test
            echo "${doc_id}|${format}|${file}" >> "/tmp/test_docs_5k.txt"
        else
            FAILED=$((FAILED + 1))
            echo -e "${RED}   ❌ FALHOU${NC} - No document_id" | tee -a "$RESULTS_LOG"
        fi
    else
        FAILED=$((FAILED + 1))
        echo -e "${RED}   ❌ FALHOU${NC}" | tee -a "$RESULTS_LOG"
        log "   Response: $(echo "$response" | head -c 200)"
    fi
}

test_bulk_upload() {
    log ""
    log "═══════════════════════════════════════════════════════════════════════"
    log "🔄 TESTE BULK UPLOAD (3 ARQUIVOS SIMULTÂNEOS)"
    log "═══════════════════════════════════════════════════════════════════════"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    # Select 3 different files
    local files=("${TEST_DIR}/test_1000pages.pdf" "${TEST_DIR}/test_2000pages.docx" "${TEST_DIR}/test_50000lines.txt")
    
    response=$(curl -s -X POST "${BASE_URL}/api/rag/bulk-ingest" \
        -F "files[]=@${files[0]}" \
        -F "files[]=@${files[1]}" \
        -F "files[]=@${files[2]}" \
        -F "user_id=1" \
        2>&1)
    
    if echo "$response" | grep -q '"success":true'; then
        success_count=$(echo "$response" | grep -o '"success_count":[0-9]*' | grep -o '[0-9]*')
        
        if [ "$success_count" = "3" ]; then
            PASSED=$((PASSED + 1))
            echo -e "${GREEN}   ✅ BULK UPLOAD PASSOU${NC}" | tee -a "$RESULTS_LOG"
            log "   3/3 arquivos processados com sucesso"
        else
            FAILED=$((FAILED + 1))
            echo -e "${RED}   ❌ BULK UPLOAD FALHOU${NC}" | tee -a "$RESULTS_LOG"
            log "   Success count: $success_count (esperado: 3)"
        fi
    else
        FAILED=$((FAILED + 1))
        echo -e "${RED}   ❌ BULK UPLOAD FALHOU${NC}" | tee -a "$RESULTS_LOG"
    fi
}

test_rag_search() {
    log ""
    log "═══════════════════════════════════════════════════════════════════════"
    log "🔍 TESTE RAG SEARCH (3 DOCUMENTOS ALEATÓRIOS)"
    log "═══════════════════════════════════════════════════════════════════════"
    
    if [ ! -f "/tmp/test_docs_5k.txt" ]; then
        log "⚠️  Nenhum documento disponível para teste de busca"
        return
    fi
    
    # Test 3 random documents
    shuf -n 3 /tmp/test_docs_5k.txt 2>/dev/null | while IFS='|' read -r doc_id format file; do
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        
        log ""
        log "🔎 Buscando em documento ${doc_id} (${format})..."
        
        response=$(curl -s -X POST "${BASE_URL}/api/rag/python-search" \
            -H "Content-Type: application/json" \
            -d "{\"query\":\"resumo do documento\",\"document_id\":${doc_id},\"use_cache\":true}" \
            2>&1)
        
        if echo "$response" | grep -q '"ok":true\|"success":true'; then
            cache_hit=$(echo "$response" | grep -o '"cache_hit":[a-z]*' | grep -o '[a-z]*$')
            
            PASSED=$((PASSED + 1))
            echo -e "${GREEN}   ✅ BUSCA OK${NC}" | tee -a "$RESULTS_LOG"
            log "   Cache hit: ${cache_hit:-false}"
        else
            FAILED=$((FAILED + 1))
            echo -e "${RED}   ❌ BUSCA FALHOU${NC}" | tee -a "$RESULTS_LOG"
        fi
    done
}

# Main execution
log "🚀 Iniciando testes..."
log "Data/Hora: $(date)"
log "Base URL: ${BASE_URL}"
log "Test Dir: ${TEST_DIR}"
log ""

# Check if test files exist
if [ ! -d "$TEST_DIR" ]; then
    echo -e "${RED}❌ Diretório de testes não encontrado: $TEST_DIR${NC}"
    echo "Execute antes: python3 generate_large_test_files.py"
    exit 1
fi

# Clear previous test results
rm -f /tmp/test_docs_5k.txt

log "═══════════════════════════════════════════════════════════════════════"
log "FASE 1: TESTES INDIVIDUAIS POR FORMATO"
log "═══════════════════════════════════════════════════════════════════════"

# Test each format
test_format "${TEST_DIR}/test_1000pages.pdf" "PDF" 1000
test_format "${TEST_DIR}/test_3000pages.pdf" "PDF" 3000
test_format "${TEST_DIR}/test_5000pages.pdf" "PDF" 5000
test_format "${TEST_DIR}/test_2000pages.docx" "DOCX" 2000
test_format "${TEST_DIR}/test_10000rows.xlsx" "XLSX" 200
test_format "${TEST_DIR}/test_500slides.pptx" "PPTX" 500
test_format "${TEST_DIR}/test_50000lines.txt" "TXT" "~12500"
test_format "${TEST_DIR}/test_50000rows.csv" "CSV" "~12500"
test_format "${TEST_DIR}/test_1000sections.html" "HTML" "~250"
test_format "${TEST_DIR}/test_10000records.xml" "XML" "~2500"
test_format "${TEST_DIR}/test_1000pages.rtf" "RTF" 1000

# Test bulk upload
test_bulk_upload

# Test RAG search
test_rag_search

# Final report
log ""
log "═══════════════════════════════════════════════════════════════════════"
log "📊 RELATÓRIO FINAL"
log "═══════════════════════════════════════════════════════════════════════"
log "Total de testes: $TOTAL_TESTS"
log "Passou: $PASSED"
log "Falhou: $FAILED"
log "Taxa de sucesso: $(awk "BEGIN {printf \"%.1f\", ($PASSED/$TOTAL_TESTS)*100}")%"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ TODOS OS TESTES PASSARAM!${NC}" | tee -a "$RESULTS_LOG"
    EXIT_CODE=0
else
    echo -e "${RED}❌ $FAILED TESTE(S) FALHARAM${NC}" | tee -a "$RESULTS_LOG"
    EXIT_CODE=1
fi

log ""
log "📝 Log completo salvo em: $RESULTS_LOG"
log ""

exit $EXIT_CODE

