#!/bin/bash
# Development startup script for Enterprise Document Extraction API

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[DEV]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE} Document Extraction API - DEV${NC}"
    echo -e "${BLUE}================================${NC}"
}

# Print header
print_header

# Set working directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

print_status "Development mode startup"
print_status "Working directory: $SCRIPT_DIR"

# Check Python
if ! command -v python3 &> /dev/null; then
    print_error "Python 3 is required"
    exit 1
fi

# Create/activate virtual environment
if [ ! -d "venv" ]; then
    print_status "Creating virtual environment..."
    python3 -m venv venv
fi

print_status "Activating virtual environment..."
source venv/bin/activate

# Install dependencies
print_status "Installing dependencies..."
pip install --upgrade pip
pip install -r requirements_enterprise.txt

# Development environment variables
export PYTHONPATH="$SCRIPT_DIR/api:$SCRIPT_DIR:$PYTHONPATH"
export API_ENVIRONMENT=development
export API_DEBUG=true
export API_PORT=8001
export API_HOST=0.0.0.0

# Development API keys (not secure, for dev only)
export API_API_KEYS="dev-key-123,test-key-456,demo-key-789"
export API_SECRET_KEY="development-secret-key-not-for-production"

# Redis (optional for development)
export API_REDIS_URL=${API_REDIS_URL:-redis://localhost:6379/0}

# Enable all features for development
export API_ENABLE_BATCH_PROCESSING=true
export API_ENABLE_URL_EXTRACTION=true
export API_ENABLE_WEBHOOKS=true
export API_ENABLE_METRICS=true

# Verbose logging
export API_LOG_LEVEL=DEBUG
export API_LOG_FORMAT=text

# Create logs directory
mkdir -p logs

print_status "Development configuration:"
echo "  Debug: $API_DEBUG"
echo "  Port: $API_PORT"
echo "  Log Level: $API_LOG_LEVEL"
echo "  API Keys: dev-key-123, test-key-456, demo-key-789"
echo

print_status "Starting development server with hot reload..."
print_status "API Documentation: http://localhost:$API_PORT/docs"
print_status "Health Check: http://localhost:$API_PORT/health"
print_status "Metrics: http://localhost:$API_PORT/metrics"
echo

# Start development server
cd api && exec uvicorn main:app \
    --host "$API_HOST" \
    --port "$API_PORT" \
    --reload \
    --log-config null \
    --reload-dir . \
    --reload-dir ../document_extraction