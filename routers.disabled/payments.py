"""
Payments Router
Sistema de pagamentos para FastAPI
"""

from fastapi import APIRouter, HTTPException, Depends, status
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
import psycopg2
from psycopg2.extras import RealDictCursor
from datetime import datetime

from routers.auth import get_current_user

router = APIRouter()

# Configuração do banco
DB_CONFIG = {
    "host": "127.0.0.1",
    "port": "5432",
    "database": "laravel_rag",
    "user": "postgres",
    "password": "postgres"
}

def get_db_connection():
    """Obter conexão com o banco"""
    return psycopg2.connect(**DB_CONFIG)

class PaymentRequest(BaseModel):
    plan: str
    amount: float
    payment_method: str = "credit_card"

class PaymentResponse(BaseModel):
    success: bool
    payment_id: str
    status: str
    message: str

class PlanResponse(BaseModel):
    success: bool
    plans: List[Dict[str, Any]]

@router.get("/plans", response_model=PlanResponse)
async def get_plans():
    """Obter planos disponíveis"""
    plans = [
        {
            "id": "free",
            "name": "Free",
            "display_name": "Plano Gratuito",
            "price_monthly": 0,
            "price_yearly": 0,
            "tokens_limit": 100,
            "documents_limit": 1,
            "features": [
                "100 tokens por mês",
                "1 documento",
                "Suporte básico"
            ],
            "description": "Perfeito para testar o sistema"
        },
        {
            "id": "pro",
            "name": "Pro",
            "display_name": "Plano Pro",
            "price_monthly": 15.00,
            "price_yearly": 150.00,
            "tokens_limit": 10000,
            "documents_limit": 50,
            "features": [
                "10.000 tokens por mês",
                "50 documentos",
                "Suporte prioritário",
                "Processamento de vídeos"
            ],
            "description": "Ideal para uso profissional"
        },
        {
            "id": "enterprise",
            "name": "Enterprise",
            "display_name": "Plano Enterprise",
            "price_monthly": 30.00,
            "price_yearly": 300.00,
            "tokens_limit": -1,  # Ilimitado
            "documents_limit": -1,  # Ilimitado
            "features": [
                "Tokens ilimitados",
                "Documentos ilimitados",
                "Suporte 24/7",
                "API personalizada",
                "Integração customizada"
            ],
            "description": "Para empresas que precisam de máxima performance"
        }
    ]
    
    return PlanResponse(
        success=True,
        plans=plans
    )

@router.post("/create-payment", response_model=PaymentResponse)
async def create_payment(request: PaymentRequest, current_user: dict = Depends(get_current_user)):
    """Criar pagamento"""
    # Validar plano
    valid_plans = ["free", "pro", "enterprise"]
    if request.plan not in valid_plans:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Plano inválido"
        )
    
    # Para plano free, não precisa de pagamento
    if request.plan == "free":
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE users SET plan = %s, tokens_limit = %s, documents_limit = %s WHERE id = %s",
                    ("free", 100, 1, current_user['id'])
                )
                conn.commit()
                
                return PaymentResponse(
                    success=True,
                    payment_id="free_upgrade",
                    status="approved",
                    message="Plano Free ativado com sucesso"
                )
        finally:
            conn.close()
    
    # Simular pagamento (em produção, integrar com gateway real)
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Criar registro de pagamento
            cursor.execute(
                """
                INSERT INTO payments (user_id, amount, status, payment_method, plan, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                RETURNING id
                """,
                (
                    current_user['id'],
                    request.amount,
                    "pending",
                    request.payment_method,
                    request.plan,
                    datetime.utcnow(),
                    datetime.utcnow()
                )
            )
            payment_id = cursor.fetchone()['id']
            
            # Simular aprovação do pagamento
            cursor.execute(
                "UPDATE payments SET status = 'approved' WHERE id = %s",
                (payment_id,)
            )
            
            # Atualizar plano do usuário
            if request.plan == "pro":
                cursor.execute(
                    "UPDATE users SET plan = %s, tokens_limit = %s, documents_limit = %s WHERE id = %s",
                    ("pro", 10000, 50, current_user['id'])
                )
            elif request.plan == "enterprise":
                cursor.execute(
                    "UPDATE users SET plan = %s, tokens_limit = %s, documents_limit = %s WHERE id = %s",
                    ("enterprise", -1, -1, current_user['id'])
                )
            
            conn.commit()
            
            return PaymentResponse(
                success=True,
                payment_id=str(payment_id),
                status="approved",
                message="Pagamento aprovado e plano ativado com sucesso"
            )
    finally:
        conn.close()

@router.get("/history")
async def payment_history(current_user: dict = Depends(get_current_user)):
    """Histórico de pagamentos"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(
                """
                SELECT id, amount, status, payment_method, plan, created_at 
                FROM payments 
                WHERE user_id = %s 
                ORDER BY created_at DESC
                """,
                (current_user['id'],)
            )
            payments = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "payments": payments
            }
    finally:
        conn.close()

@router.get("/current-plan")
async def get_current_plan(current_user: dict = Depends(get_current_user)):
    """Obter plano atual do usuário"""
    return {
        "success": True,
        "plan": {
            "id": current_user.get('plan', 'free'),
            "tokens_used": current_user.get('tokens_used', 0),
            "tokens_limit": current_user.get('tokens_limit', 100),
            "documents_used": current_user.get('documents_used', 0),
            "documents_limit": current_user.get('documents_limit', 1)
        }
    }

@router.post("/webhook")
async def payment_webhook(request: dict):
    """Webhook para notificações de pagamento"""
    # Em produção, validar assinatura do webhook
    # e processar notificações do gateway de pagamento
    
    return {
        "success": True,
        "message": "Webhook processado com sucesso"
    }
