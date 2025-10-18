<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MercadoPagoService
{
    private string $accessToken;
    private string $publicKey;
    private string $baseUrl;
    private bool $sandbox;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token') ?: 'TEST-ACCESS-TOKEN';
        $this->publicKey = config('services.mercadopago.public_key') ?: 'TEST-PUBLIC-KEY';
        $this->sandbox = config('services.mercadopago.sandbox', true);
        $this->baseUrl = $this->sandbox ? 'https://api.mercadopago.com' : 'https://api.mercadopago.com';
        
        // Em desenvolvimento, permitir tokens de teste
        if (!$this->accessToken || $this->accessToken === 'TEST-ACCESS-TOKEN') {
            if (config('app.env') === 'production') {
                throw new Exception('Mercado Pago access token não configurado');
            }
            // Em desenvolvimento, usar tokens de teste
            $this->accessToken = 'TEST-ACCESS-TOKEN';
            $this->publicKey = 'TEST-PUBLIC-KEY';
        }
    }

    /**
     * Cria uma preferência de pagamento
     */
    public function createPreference(array $data): array
    {
        $preference = [
            'items' => [
                [
                    'title' => $data['title'],
                    'quantity' => 1,
                    'unit_price' => (float) $data['amount'],
                    'currency_id' => 'BRL'
                ]
            ],
            'payer' => [
                'email' => $data['user_email'],
                'name' => $data['user_name'] ?? '',
            ],
            'back_urls' => [
                'success' => $data['success_url'] ?? url('/billing/success'),
                'failure' => $data['failure_url'] ?? url('/billing/failure'),
                'pending' => $data['pending_url'] ?? url('/billing/pending')
            ],
            'auto_return' => 'approved',
            'external_reference' => $data['external_reference'],
            'notification_url' => $data['notification_url'],
            'payment_methods' => [
                'excluded_payment_methods' => [],
                'excluded_payment_types' => [],
                'installments' => 12
            ],
            'metadata' => [
                'user_id' => $data['user_id'],
                'plan_id' => $data['plan_id'] ?? null,
                'subscription_id' => $data['subscription_id'] ?? null,
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => uniqid()
            ])->post($this->baseUrl . '/checkout/preferences', $preference);

            if ($response->successful()) {
                Log::info('Mercado Pago preference created', [
                    'preference_id' => $response->json('id'),
                    'external_reference' => $data['external_reference'],
                    'amount' => $data['amount']
                ]);

                return $response->json();
            }

            Log::error('Mercado Pago preference creation failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'data' => $data
            ]);

            throw new Exception('Erro ao criar preferência: ' . $response->body());

        } catch (Exception $e) {
            Log::error('Mercado Pago service error', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Obtém informações de um pagamento
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/v1/payments/' . $paymentId);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Mercado Pago payment retrieval failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            throw new Exception('Erro ao buscar pagamento: ' . $response->body());

        } catch (Exception $e) {
            Log::error('Mercado Pago payment retrieval error', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);
            throw $e;
        }
    }

    /**
     * Obtém informações de uma preferência
     */
    public function getPreference(string $preferenceId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/checkout/preferences/' . $preferenceId);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Erro ao buscar preferência: ' . $response->body());

        } catch (Exception $e) {
            Log::error('Mercado Pago preference retrieval error', [
                'error' => $e->getMessage(),
                'preference_id' => $preferenceId
            ]);
            throw $e;
        }
    }

    /**
     * Valida webhook do Mercado Pago
     */
    public function validateWebhook(array $data, string $signature): bool
    {
        // Em produção, validar a assinatura do webhook
        // Por enquanto, apenas validamos se os dados estão presentes
        return isset($data['data']['id']) && isset($data['type']);
    }

    /**
     * Processa notificação de webhook
     */
    public function processWebhookNotification(array $data): array
    {
        $notificationType = $data['type'] ?? '';
        $resourceId = $data['data']['id'] ?? '';

        Log::info('Mercado Pago webhook received', [
            'type' => $notificationType,
            'resource_id' => $resourceId
        ]);

        switch ($notificationType) {
            case 'payment':
                return $this->processPaymentNotification($resourceId);
            case 'preference':
                return $this->processPreferenceNotification($resourceId);
            default:
                Log::warning('Unknown Mercado Pago notification type', [
                    'type' => $notificationType,
                    'data' => $data
                ]);
                return ['status' => 'ignored', 'message' => 'Unknown notification type'];
        }
    }

    /**
     * Processa notificação de pagamento
     */
    private function processPaymentNotification(string $paymentId): array
    {
        try {
            $payment = $this->getPayment($paymentId);
            
            return [
                'status' => 'processed',
                'payment_id' => $paymentId,
                'payment_status' => $payment['status'] ?? 'unknown',
                'external_reference' => $payment['external_reference'] ?? null,
                'amount' => $payment['transaction_amount'] ?? 0,
                'payment_method' => $payment['payment_method_id'] ?? 'unknown'
            ];

        } catch (Exception $e) {
            Log::error('Error processing payment notification', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Processa notificação de preferência
     */
    private function processPreferenceNotification(string $preferenceId): array
    {
        try {
            $preference = $this->getPreference($preferenceId);
            
            return [
                'status' => 'processed',
                'preference_id' => $preferenceId,
                'external_reference' => $preference['external_reference'] ?? null,
                'status' => $preference['status'] ?? 'unknown'
            ];

        } catch (Exception $e) {
            Log::error('Error processing preference notification', [
                'preference_id' => $preferenceId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Retorna configurações do frontend
     */
    public function getFrontendConfig(): array
    {
        return [
            'public_key' => $this->publicKey,
            'sandbox' => $this->sandbox,
            'locale' => 'pt-BR',
            'currency' => 'BRL'
        ];
    }

    /**
     * Calcula taxas do Mercado Pago
     */
    public function calculateFees(float $amount): array
    {
        // Taxas aproximadas do Mercado Pago
        $mercadopagoFee = $amount * 0.0499 + 0.39; // 4.99% + R$ 0,39
        $netAmount = $amount - $mercadopagoFee;

        return [
            'gross_amount' => $amount,
            'mercadopago_fee' => round($mercadopagoFee, 2),
            'net_amount' => round($netAmount, 2),
            'fee_percentage' => 4.99
        ];
    }

    /**
     * Testa conectividade com a API
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/users/me');

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Mercado Pago connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
