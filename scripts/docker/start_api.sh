#!/bin/bash
# Production startup script for Enterprise Document Extraction API

set -e

echo "Starting Enterprise Document Extraction API..."

# Set default environment variables if not provided
export API_ENVIRONMENT=${API_ENVIRONMENT:-production}
export API_DEBUG=${API_DEBUG:-false}
export API_PORT=${API_PORT:-8001}
export API_WORKERS=${API_WORKERS:-4}

# Set Python path
export PYTHONPATH=/app

# Check if running in development mode
if [ "$API_ENVIRONMENT" = "development" ]; then
    echo "Running in development mode with hot reload..."
    exec uvicorn api.main:app \
        --host 0.0.0.0 \
        --port $API_PORT \
        --reload \
        --log-config null
else
    echo "Running in production mode..."

    # Calculate optimal number of workers if not set
    if [ -z "$API_WORKERS" ]; then
        API_WORKERS=$(($(nproc) * 2 + 1))
        echo "Auto-calculated workers: $API_WORKERS"
    fi

    # Use Gunicorn for production
    exec gunicorn api.main:app \
        --worker-class uvicorn.workers.UvicornWorker \
        --workers $API_WORKERS \
        --bind 0.0.0.0:$API_PORT \
        --timeout 300 \
        --keep-alive 2 \
        --max-requests 1000 \
        --max-requests-jitter 100 \
        --access-logfile - \
        --error-logfile - \
        --log-level info \
        --preload
fi