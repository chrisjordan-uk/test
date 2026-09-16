#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -d ".venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv .venv
fi

# shellcheck disable=SC1091
source .venv/bin/activate

echo "Installing dependencies..."
pip install -r requirements.txt

if [ ! -f ".env" ]; then
    echo "No .env found — copying .env.example. Edit .env to add your GEMINI_API_KEY."
    cp .env.example .env
fi

echo "Starting Jordyn AI Photo Studio..."
python -m streamlit run app/main.py
