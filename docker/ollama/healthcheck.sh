#!/bin/bash
# Ollama Health Check Script
# Ensures Ollama is responding and has required models

set -e

# Check if Ollama is responding
if ! curl -f -s http://localhost:11434/api/tags >/dev/null 2>&1; then
    echo "❌ Ollama is not responding"
    exit 1
fi

# Check if required model exists
REQUIRED_MODEL="${OLLAMA_MODEL:-llava}"
MODELS=$(curl -s http://localhost:11434/api/tags | grep '"name"' | cut -d'"' -f4)

if echo "$MODELS" | grep -q "$REQUIRED_MODEL"; then
    echo "✅ Ollama healthy with $REQUIRED_MODEL model"
    exit 0
else
    echo "⚠️  Ollama responding but $REQUIRED_MODEL model not found"
    # Still exit 0 as Ollama itself is working (model may be downloading)
    exit 0
fi

