@echo off
setlocal

cd /d "%~dp0"

if not exist ".venv" (
    echo Creating virtual environment...
    python -m venv .venv
)

call .venv\Scripts\activate.bat

echo Installing dependencies...
pip install -r requirements.txt

if not exist ".env" (
    echo No .env found — copying .env.example. Edit .env to add your GEMINI_API_KEY.
    copy .env.example .env
)

echo Starting Jordyn AI Photo Studio...
python -m streamlit run app\main.py

endlocal
