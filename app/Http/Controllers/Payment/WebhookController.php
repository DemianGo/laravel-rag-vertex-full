<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    private MercadoPagoService $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Processa webhook do Mercado Pago
     */
    public function handle(Request $request)
    {
        Log::info('Webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip()
        ]);

        try {
            // Validar se é uma notificação válida do Mercado Pago
            $data = $request->all();
            
            if (!$this->mercadoPagoService->validateWebhook($data, $request->header('x-signature'))) {
                Log::warning('Invalid webhook signature', [
                    'data' => $data,
                    'signature' => $request->header('x-signature')
                ]);
                
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Processar notificação
            $result = $this->mercadoPagoService->processWebhookNotification($data);

            if ($result['status'] === 'processed') {
                $this->handleProcessedNotification($result);
            }

            return response()->json(['status' => 'ok']);

        } catch (Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Processa notificação já validada
     */
    private function handleProcessedNotification(array $result): void
    {
        $paymentId = $result['payment_id'] ?? null;
        $paymentStatus = $result['payment_status'] ?? null;
        $externalReference = $result['external_reference'] ?? null;

        if (!$paymentId || !$externalReference) {
            Log::warning('Incomplete webhook data', ['result' => $result]);
            return;
        }

        // Buscar pagamento pelo external_reference ou payment_id
        $payment = Payment::where('external_id', $paymentId)
            ->orWhere('metadata->external_reference', $externalReference)
            ->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'payment_id' => $paymentId,
                'external_reference' => $externalReference
            ]);
            return;
        }

        // Atualizar status do pagamento
        $this->updatePaymentStatus($payment, $paymentStatus, $result);

        Log::info('Webhook processed successfully', [
            'payment_id' => $payment->id,
            'status' => $paymentStatus,
            'external_reference' => $externalReference
        ]);
    }

    /**
     * Atualiza status do pagamento baseado na notificação
     */
    private function updatePaymentStatus(Payment $payment, string $status, array $webhookData): void
    {
        $oldStatus = $payment->status;

        switch ($status) {
            case 'approved':
                if ($payment->status !== 'approved') {
                    $this->approvePayment($payment, $webhookData);
                }
                break;

            case 'rejected':
            case 'cancelled':
                if ($payment->status !== 'rejected') {
                    $payment->reject('Pagamento rejeitado pelo gateway');
                }
                break;

            case 'pending':
                // Manter status pending - não fazer nada
                break;

            default:
                Log::warning('Unknown payment status received', [
                    'payment_id' => $payment->id,
                    'status' => $status,
                    'webhook_data' => $webhookData
                ]);
        }

        // Atualizar gateway_data com informações mais recentes
        $payment->update([
            'gateway_data' => array_merge($payment->gateway_data ?? [], $webhookData)
        ]);

        Log::info('Payment status updated', [
            'payment_id' => $payment->id,
            'old_status' => $oldStatus,
            'new_status' => $payment->fresh()->status,
            'webhook_status' => $status
        ]);
    }

    /**
     * Aprova pagamento e ativa assinatura
     */
    private function approvePayment(Payment $payment, array $webhookData): void
    {
        $payment->approve();

        // Ativar assinatura
        $subscription = $payment->subscription;
        if ($subscription && $subscription->status === 'pending') {
            $subscription->activate();

            // Atualizar usuário
            $user = $payment->user;
            $plan = $subscription->planConfig;
            
            $user->update([
                'plan' => $plan->plan_name,
                'tokens_limit' => $plan->tokens_limit,
                'documents_limit' => $plan->documents_limit,
                'tokens_used' => 0, // Reset tokens
                'documents_used' => 0, // Reset documents
            ]);

            Log::info('Payment approved and subscription activated via webhook', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan' => $plan->plan_name,
                'amount' => $payment->amount
            ]);
        }
    }

    /**
     * Testa conectividade com Mercado Pago
     */
    public function test()
    {
        try {
            $isConnected = $this->mercadoPagoService->testConnection();
            
            return response()->json([
                'connected' => $isConnected,
                'message' => $isConnected ? 'Conexão OK' : 'Falha na conexão',
                'timestamp' => now()->toISOString()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'connected' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
}
