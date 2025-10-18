<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\BypassUploadController;
use App\Http\Controllers\Payment\SubscriptionController;
use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\Payment\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Redireciona usuários autenticados para /rag-frontend
    if (auth()->check()) {
        return redirect('/rag-frontend');
    }
    return view('welcome');
});

// Pricing Routes
Route::get('/precos', [\App\Http\Controllers\PricingController::class, 'index'])->name('pricing.index');
Route::post('/checkout', [\App\Http\Controllers\PricingController::class, 'checkout'])->name('pricing.checkout');
Route::get('/pricing/success', [\App\Http\Controllers\PricingController::class, 'success'])->name('pricing.success');
Route::get('/pricing/failure', [\App\Http\Controllers\PricingController::class, 'failure'])->name('pricing.failure');
Route::get('/pricing/pending', [\App\Http\Controllers\PricingController::class, 'pending'])->name('pricing.pending');



Route::middleware(['auth'])->group(function () {
    // RAG Frontend (página principal - PROTEGIDA)
    Route::match(['get', 'head'], '/rag-frontend', function () {
        $htmlPath = resource_path('views/rag-frontend-static/index.html.protected');
        
        if (file_exists($htmlPath)) {
            $content = file_get_contents($htmlPath);
            
            // Injeta o CSRF token no HTML
            $csrfToken = csrf_token();
            $content = str_replace(
                '<meta name="csrf-token" content="">',
                '<meta name="csrf-token" content="' . $csrfToken . '">',
                $content
            );
            
            // Também injeta no campo hidden do form de logout
            $content = str_replace(
                '<input type="hidden" name="_token" id="csrfToken" value="">',
                '<input type="hidden" name="_token" id="csrfToken" value="' . $csrfToken . '">',
                $content
            );
            
            return response($content)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }
        
        abort(404, 'RAG Frontend não encontrado');
    })->name('rag-frontend');
    
    // User info API (JSON endpoint for authenticated users)
    Route::get('/api/user/info', function () {
        return response()->json([
            'user' => [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'plan' => auth()->user()->plan,
                'tokens_used' => auth()->user()->tokens_used,
                'tokens_limit' => auth()->user()->tokens_limit,
            ],
            'csrf_token' => csrf_token()
        ]);
    });
    
    // RAG API routes are in api.php with auth:sanctum middleware

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/query', [ChatController::class, 'query'])->name('chat.query');

    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');

    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');

    // Bypass Upload System (fast alternative)
    Route::get('/upload-bypass', [BypassUploadController::class, 'index'])->name('upload-bypass.index');
    Route::post('/upload-bypass', [BypassUploadController::class, 'upload'])->name('upload-bypass.upload');
    Route::post('/upload-bypass/process-advanced', [BypassUploadController::class, 'processAdvanced'])->name('upload-bypass.process-advanced');
    Route::get('/api/bypass-documents', [BypassUploadController::class, 'list'])->name('upload-bypass.list');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes (protected by admin middleware)
Route::prefix('admin')->group(function () {
    // Auth routes (not protected)
    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('admin.logout');
    
        // Protected admin routes
        Route::middleware(['auth', 'admin'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('admin.dashboard');
            Route::get('/finance', [\App\Http\Controllers\Admin\AdminController::class, 'finance'])->name('admin.finance');
            
            // Users management
            Route::get('/users', [\App\Http\Controllers\Admin\AdminController::class, 'users'])->name('admin.users.index');
            Route::get('/users/{user}', [\App\Http\Controllers\Admin\AdminController::class, 'showUser'])->name('admin.users.show');
            Route::patch('/users/{user}/toggle-admin', [\App\Http\Controllers\Admin\AdminController::class, 'toggleAdmin'])->name('admin.users.toggle-admin');
                Route::patch('/users/{user}/toggle-super-admin', [\App\Http\Controllers\Admin\AdminController::class, 'toggleSuperAdmin'])->name('admin.users.toggle-super-admin');
                
                // Plans management
                Route::get('/plans', [\App\Http\Controllers\Admin\AdminController::class, 'plans'])->name('admin.plans.index');
                Route::get('/plans/{plan}', [\App\Http\Controllers\Admin\AdminController::class, 'getPlan'])->name('admin.plans.show');
                Route::post('/plans', [\App\Http\Controllers\Admin\AdminController::class, 'storePlan'])->name('admin.plans.store');
                Route::put('/plans/{plan}', [\App\Http\Controllers\Admin\AdminController::class, 'updatePlan'])->name('admin.plans.update');
                Route::patch('/plans/{plan}/toggle', [\App\Http\Controllers\Admin\AdminController::class, 'togglePlan'])->name('admin.plans.toggle');
                
                // AI Providers management
                Route::get('/ai-providers', [\App\Http\Controllers\Admin\AdminController::class, 'aiProviders'])->name('admin.ai-providers.index');
                Route::get('/ai-providers/data', [\App\Http\Controllers\Admin\AdminController::class, 'getAiProvidersData'])->name('admin.ai-providers.data');
                Route::get('/ai-providers/{provider}', [\App\Http\Controllers\Admin\AdminController::class, 'getAiProvider'])->name('admin.ai-providers.show');
                Route::post('/ai-providers', [\App\Http\Controllers\Admin\AdminController::class, 'storeAiProvider'])->name('admin.ai-providers.store');
                Route::put('/ai-providers/{provider}', [\App\Http\Controllers\Admin\AdminController::class, 'updateAiProvider'])->name('admin.ai-providers.update');
                Route::patch('/ai-providers/{provider}/toggle', [\App\Http\Controllers\Admin\AdminController::class, 'toggleAiProvider'])->name('admin.ai-providers.toggle');
                
                // Admin Documents Routes
    Route::get('/documents/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'showDocument'])->name('admin.documents.show');
    Route::get('/documents/{id}/download', [\App\Http\Controllers\Admin\AdminController::class, 'downloadDocument'])->name('admin.documents.download');
    Route::delete('/documents/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'deleteDocument'])->name('admin.documents.delete');
    
    // Payment Management Routes
    Route::get('/payment-settings', [\App\Http\Controllers\Admin\AdminController::class, 'paymentSettings'])->name('admin.payment-settings');
    Route::post('/payment-settings', [\App\Http\Controllers\Admin\AdminController::class, 'updatePaymentSettings'])->name('admin.payment-settings.update');
    Route::post('/payment-settings/test-connection', [\App\Http\Controllers\Admin\AdminController::class, 'testMercadoPagoConnection'])->name('admin.payment-settings.test');
    Route::get('/payments', [\App\Http\Controllers\Admin\AdminController::class, 'payments'])->name('admin.payments');
    Route::get('/payments/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'paymentDetails'])->name('admin.payments.details');
    Route::put('/payments/{id}/status', [\App\Http\Controllers\Admin\AdminController::class, 'updatePaymentStatus'])->name('admin.payments.status');
    Route::get('/payment-stats', [\App\Http\Controllers\Admin\AdminController::class, 'paymentStats'])->name('admin.payment-stats');
    
            
            // Profile management
            Route::get('/profile', [\App\Http\Controllers\Admin\AuthController::class, 'profile'])->name('admin.profile');
            Route::patch('/profile', [\App\Http\Controllers\Admin\AuthController::class, 'updateProfile'])->name('admin.profile.update');
            Route::get('/change-password', [\App\Http\Controllers\Admin\AuthController::class, 'showChangePasswordForm'])->name('admin.change-password');
            Route::patch('/change-password', [\App\Http\Controllers\Admin\AuthController::class, 'changePassword'])->name('admin.change-password.update');
        });
});

// Billing Routes (new system)
Route::prefix('billing')->group(function () {
    // Public routes
    Route::get('/plans', [\App\Http\Controllers\BillingController::class, 'plans'])->name('billing.plans');
    Route::get('/success', [\App\Http\Controllers\BillingController::class, 'success'])->name('billing.success');
    Route::get('/failure', [\App\Http\Controllers\BillingController::class, 'failure'])->name('billing.failure');
    Route::get('/pending', [\App\Http\Controllers\BillingController::class, 'pending'])->name('billing.pending');
    
    // Webhook (no auth required)
    Route::post('/webhook', [\App\Http\Controllers\BillingController::class, 'webhook'])->name('billing.webhook');
    
    // Protected routes
    Route::middleware(['auth'])->group(function () {
        Route::post('/select-plan', [\App\Http\Controllers\BillingController::class, 'selectPlan'])->name('billing.select-plan');
        Route::post('/charge-tokens', [\App\Http\Controllers\BillingController::class, 'chargeTokens'])->name('billing.charge-tokens');
        Route::post('/calculate-ai-cost', [\App\Http\Controllers\BillingController::class, 'calculateAiCost'])->name('billing.calculate-ai-cost');
        Route::get('/test', [\App\Http\Controllers\BillingController::class, 'testBilling'])->name('billing.test');
        Route::delete('/subscription/{subscription}/cancel', [\App\Http\Controllers\BillingController::class, 'cancelSubscription'])->name('billing.cancel-subscription');
    });
    
    // Admin routes
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/stats', [\App\Http\Controllers\BillingController::class, 'stats'])->name('billing.stats');
    });
});

// Payment Routes (legacy)
Route::prefix('payment')->group(function () {
    // Public routes (no auth required)
    Route::get('/test', function() { return view('payment.test'); })->name('payment.test');
    Route::get('/plans', [CheckoutController::class, 'plans'])->name('payment.plans');
    Route::get('/checkout', [CheckoutController::class, 'checkout'])->name('payment.checkout');
    Route::get('/config', [CheckoutController::class, 'config'])->name('payment.config');
    Route::get('/success', [SubscriptionController::class, 'success'])->name('payment.success');
    Route::get('/failure', [SubscriptionController::class, 'failure'])->name('payment.failure');
    Route::get('/pending', [SubscriptionController::class, 'pending'])->name('payment.pending');
    
    // Webhook (no auth required, but has validation)
    Route::post('/webhook', [WebhookController::class, 'handle'])->name('payment.webhook');
    Route::get('/webhook/test', [WebhookController::class, 'test'])->name('payment.webhook.test');
    
    // Protected routes (auth required)
    Route::middleware(['auth'])->group(function () {
        Route::post('/process', [CheckoutController::class, 'process'])->name('payment.process');
        Route::post('/checkout', [SubscriptionController::class, 'createCheckout'])->name('payment.create-checkout');
        Route::delete('/subscription/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('payment.cancel-subscription');
    });
});

require __DIR__.'/auth.php';
