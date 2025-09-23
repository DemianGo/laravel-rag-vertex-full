#!/bin/bash
# Production startup script for Enterprise Document Extraction API

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE} Enterprise Document Extraction API${NC}"
    echo -e "${BLUE}================================${NC}"
}

# Print header
print_header

# Check if Python is available
if ! command -v python3 &> /dev/null; then
    print_error "Python 3 is required but not installed"
    exit 1
fi

# Check Python version
PYTHON_VERSION=$(python3 --version | cut -d' ' -f2)
PYTHON_MAJOR=$(echo $PYTHON_VERSION | cut -d'.' -f1)
PYTHON_MINOR=$(echo $PYTHON_VERSION | cut -d'.' -f2)

if [ $PYTHON_MAJOR -lt 3 ] || [ $PYTHON_MAJOR -eq 3 -a $PYTHON_MINOR -lt 8 ]; then
    print_error "Python 3.8+ is required. Found: $PYTHON_VERSION"
    exit 1
fi

print_status "Python version: $PYTHON_VERSION"

# Set working directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

print_status "Working directory: $SCRIPT_DIR"

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    print_status "Creating virtual environment..."
    python3 -m venv venv
fi

# Activate virtual environment
print_status "Activating virtual environment..."
source venv/bin/activate

# Upgrade pip
print_status "Upgrading pip..."
pip install --upgrade pip

# Install dependencies
if [ -f "requirements_enterprise.txt" ]; then
    print_status "Installing enterprise dependencies..."
    pip install -r requirements_enterprise.txt
else
    print_error "requirements_enterprise.txt not found"
    exit 1
fi

# Set environment variables
export PYTHONPATH="$SCRIPT_DIR/api:$SCRIPT_DIR:$PYTHONPATH"
export API_ENVIRONMENT=${API_ENVIRONMENT:-production}
export API_DEBUG=${API_DEBUG:-false}
export API_PORT=${API_PORT:-8001}
export API_HOST=${API_HOST:-0.0.0.0}

# Set default API keys if not provided (CHANGE IN PRODUCTION!)
if [ -z "$API_API_KEYS" ]; then
    print_warning "Using default API keys. CHANGE THESE IN PRODUCTION!"
    export API_API_KEYS="prod-key-$(openssl rand -hex 16),admin-key-$(openssl rand -hex 16)"
fi

# Set Redis URL if not provided
export API_REDIS_URL=${API_REDIS_URL:-redis://localhost:6379/0}

# Set secret key if not provided
if [ -z "$API_SECRET_KEY" ]; then
    print_warning "Generating random secret key. Set API_SECRET_KEY for persistent sessions."
    export API_SECRET_KEY="$(openssl rand -hex 32)"
fi

# Check if Redis is available
print_status "Checking Redis connection..."
if ! python3 -c "
import redis
try:
    r = redis.from_url('$API_REDIS_URL')
    r.ping()
    print('Redis connection: OK')
except:
    print('Redis connection: Failed (will use in-memory cache)')
" 2>/dev/null; then
    print_warning "Redis not available, using in-memory cache"
fi

# Create logs directory
mkdir -p logs

# Check for required files
required_files=(
    "api/main.py"
    "document_extraction/main_extractor.py"
)

for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        print_error "Required file not found: $file"
        exit 1
    fi
done

print_status "All required files found"

# Display configuration
echo
print_status "Configuration:"
echo "  Environment: $API_ENVIRONMENT"
echo "  Debug: $API_DEBUG"
echo "  Host: $API_HOST"
echo "  Port: $API_PORT"
echo "  Redis URL: $API_REDIS_URL"
echo

# Start the API
print_status "Starting Enterprise Document Extraction API..."

if [ "$API_ENVIRONMENT" = "development" ]; then
    print_status "Running in development mode with auto-reload..."
    cd api && exec uvicorn main:app \
        --host "$API_HOST" \
        --port "$API_PORT" \
        --reload \
        --log-config null
else
    # Production mode - calculate optimal workers
    WORKERS=${API_WORKERS:-$(($(nproc) * 2 + 1))}
    print_status "Running in production mode with $WORKERS workers..."

    cd api && exec gunicorn main:app \
        --worker-class uvicorn.workers.UvicornWorker \
        --workers "$WORKERS" \
        --bind "$API_HOST:$API_PORT" \
        --timeout 300 \
        --keep-alive 2 \
        --max-requests 1000 \
        --max-requests-jitter 100 \
        --access-logfile ../logs/access.log \
        --error-logfile ../logs/error.log \
        --log-level info \
        --preload
fi