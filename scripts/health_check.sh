#!/bin/bash
# Health check script for Enterprise Document Extraction API

set -e

# Configuration
API_HOST=${API_HOST:-localhost}
API_PORT=${API_PORT:-8001}
API_KEY=${API_KEY:-dev-key-123}
TIMEOUT=${TIMEOUT:-30}

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

# Function to make HTTP requests
make_request() {
    local url=$1
    local method=${2:-GET}
    local headers=${3:-""}

    curl -s -m $TIMEOUT -X "$method" \
        -H "Accept: application/json" \
        $headers \
        "$url" 2>/dev/null
}

# Function to check HTTP status
check_http_status() {
    local url=$1
    local method=${2:-GET}
    local headers=${3:-""}

    curl -s -m $TIMEOUT -o /dev/null -w "%{http_code}" -X "$method" \
        -H "Accept: application/json" \
        $headers \
        "$url" 2>/dev/null
}

# Main health check function
main() {
    local base_url="http://$API_HOST:$API_PORT"
    local failed_checks=0

    echo "🏥 Enterprise Document Extraction API Health Check"
    echo "=================================================="
    echo "Target: $base_url"
    echo "Timeout: ${TIMEOUT}s"
    echo

    # 1. Basic connectivity
    print_info "1. Checking basic connectivity..."
    status=$(check_http_status "$base_url/")
    if [ "$status" = "200" ]; then
        print_status "API is reachable"
    else
        print_error "API is not reachable (HTTP $status)"
        ((failed_checks++))
    fi

    # 2. Simple health check
    print_info "2. Checking simple health endpoint..."
    status=$(check_http_status "$base_url/health/simple")
    if [ "$status" = "200" ]; then
        response=$(make_request "$base_url/health/simple")
        if echo "$response" | grep -q '"status": "ok"'; then
            print_status "Simple health check passed"
        else
            print_warning "Simple health check returned unexpected response"
            ((failed_checks++))
        fi
    else
        print_error "Simple health check failed (HTTP $status)"
        ((failed_checks++))
    fi

    # 3. Comprehensive health check
    print_info "3. Checking comprehensive health endpoint..."
    status=$(check_http_status "$base_url/health")
    if [ "$status" = "200" ]; then
        response=$(make_request "$base_url/health")
        overall_status=$(echo "$response" | grep -o '"overall_status": "[^"]*"' | cut -d'"' -f4)

        if [ "$overall_status" = "healthy" ]; then
            print_status "Comprehensive health check: $overall_status"
        elif [ "$overall_status" = "degraded" ]; then
            print_warning "Comprehensive health check: $overall_status"
        else
            print_error "Comprehensive health check: $overall_status"
            ((failed_checks++))
        fi
    else
        print_error "Comprehensive health check failed (HTTP $status)"
        ((failed_checks++))
    fi

    # 4. Readiness check
    print_info "4. Checking readiness endpoint..."
    status=$(check_http_status "$base_url/ready")
    if [ "$status" = "200" ]; then
        response=$(make_request "$base_url/ready")
        if echo "$response" | grep -q '"ready": true'; then
            print_status "Readiness check passed"
        else
            print_warning "Service is not ready"
            ((failed_checks++))
        fi
    else
        print_error "Readiness check failed (HTTP $status)"
        ((failed_checks++))
    fi

    # 5. Liveness check
    print_info "5. Checking liveness endpoint..."
    status=$(check_http_status "$base_url/live")
    if [ "$status" = "200" ]; then
        response=$(make_request "$base_url/live")
        if echo "$response" | grep -q '"alive": true'; then
            print_status "Liveness check passed"
        else
            print_error "Liveness check failed"
            ((failed_checks++))
        fi
    else
        print_error "Liveness check failed (HTTP $status)"
        ((failed_checks++))
    fi

    # 6. Metrics endpoint
    print_info "6. Checking metrics endpoint..."
    status=$(check_http_status "$base_url/metrics")
    if [ "$status" = "200" ]; then
        response=$(make_request "$base_url/metrics")
        if echo "$response" | grep -q "http_requests_total"; then
            print_status "Metrics endpoint is working"
        else
            print_warning "Metrics endpoint returned unexpected format"
        fi
    else
        print_error "Metrics endpoint failed (HTTP $status)"
        ((failed_checks++))
    fi

    # 7. Authentication test
    print_info "7. Testing API authentication..."
    headers="-H \"Authorization: Bearer $API_KEY\""
    status=$(check_http_status "$base_url/v1/formats" "GET" "$headers")
    if [ "$status" = "200" ]; then
        print_status "API authentication is working"
    elif [ "$status" = "401" ]; then
        print_warning "API authentication failed - check API key"
        ((failed_checks++))
    else
        print_error "Formats endpoint failed (HTTP $status)"
        ((failed_checks++))
    fi

    # 8. Rate limiting test
    print_info "8. Testing rate limiting..."
    headers="-H \"Authorization: Bearer $API_KEY\""
    # Make multiple rapid requests to test rate limiting
    for i in {1..5}; do
        status=$(check_http_status "$base_url/v1/formats" "GET" "$headers")
        if [ "$status" = "429" ]; then
            print_status "Rate limiting is working (got 429)"
            break
        elif [ "$i" = "5" ]; then
            print_info "Rate limiting test completed (no limits hit)"
        fi
        sleep 0.1
    done

    echo
    echo "=================================================="

    if [ $failed_checks -eq 0 ]; then
        print_status "All health checks passed! 🎉"
        echo "The API is healthy and ready to serve requests."
        exit 0
    else
        print_error "$failed_checks health check(s) failed! 💥"
        echo "The API may not be functioning properly."
        exit 1
    fi
}

# Help function
show_help() {
    echo "Usage: $0 [OPTIONS]"
    echo
    echo "Health check script for Enterprise Document Extraction API"
    echo
    echo "Options:"
    echo "  -h, --host HOST      API host (default: localhost)"
    echo "  -p, --port PORT      API port (default: 8001)"
    echo "  -k, --key KEY        API key for authentication (default: dev-key-123)"
    echo "  -t, --timeout SEC    Request timeout in seconds (default: 30)"
    echo "  --help               Show this help message"
    echo
    echo "Environment variables:"
    echo "  API_HOST             API host"
    echo "  API_PORT             API port"
    echo "  API_KEY              API key"
    echo "  TIMEOUT              Request timeout"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--host)
            API_HOST="$2"
            shift 2
            ;;
        -p|--port)
            API_PORT="$2"
            shift 2
            ;;
        -k|--key)
            API_KEY="$2"
            shift 2
            ;;
        -t|--timeout)
            TIMEOUT="$2"
            shift 2
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Run health check
main