#!/usr/bin/env python3
"""
Teste simples do FastAPI
"""

from fastapi import FastAPI
import uvicorn

app = FastAPI(title="Test FastAPI")

@app.get("/")
async def root():
    return {"message": "FastAPI funcionando!"}

@app.get("/health")
async def health():
    return {"status": "healthy", "service": "FastAPI Test"}

if __name__ == "__main__":
    print("🚀 Iniciando teste FastAPI...")
    uvicorn.run(app, host="0.0.0.0", port=8000)
