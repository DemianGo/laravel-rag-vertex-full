<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Document;
use App\Models\PlanConfig;
use App\Models\SystemConfig;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /**
     * Dashboard principal do admin
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('email_verified_at', '!=', null)->count(),
            'admin_users' => User::where('is_admin', true)->count(),
            'total_subscriptions' => Subscription::count(),
            'active_subscriptions' => Subscription::active()->count(),
            'pending_subscriptions' => Subscription::pending()->count(),
            'total_payments' => Payment::count(),
            'approved_payments' => Payment::approved()->count(),
            'monthly_revenue' => Payment::thisMonth()->approved()->sum('amount'),
            'yearly_revenue' => Payment::thisYear()->approved()->sum('amount'),
            'total_documents' => Document::count(),
            'documents_this_month' => Document::whereMonth('created_at', now()->month)->count(),
        ];

        // Gráfico de receita dos últimos 6 meses
        $revenueChart = $this->getRevenueChart();
        
        // Gráfico de novos usuários dos últimos 6 meses
        $usersChart = $this->getUsersChart();

        // Planos mais populares
        $popularPlans = $this->getPopularPlans();

        // Pagamentos recentes
        $recentPayments = Payment::with(['user', 'subscription.planConfig'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 
            'revenueChart', 
            'usersChart', 
            'popularPlans', 
            'recentPayments'
        ));
    }

    /**
     * Lista de usuários
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('plan')) {
            $query->whereHas('userPlan', function($q) use ($request) {
                $q->where('plan', $request->plan);
            });
        }

        if ($request->filled('is_admin')) {
            $query->where('is_admin', $request->is_admin === '1');
        }

        $users = $query->with(['userPlan', 'subscriptions.planConfig'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Detalhes de um usuário
     */
    public function showUser(User $user)
    {
        $user->load([
            'userPlan',
            'subscriptions.planConfig',
            'payments' => function($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        // Carregar documentos do usuário manualmente devido ao tenant_slug
        $documents = $user->getDocuments();

        return view('admin.users.show', compact('user', 'documents'));
    }

    /**
     * Toggle admin status de um usuário
     */
    public function toggleAdmin(User $user)
    {
        $user->update(['is_admin' => !$user->is_admin]);
        
        return redirect()->back()->with('success', 
            'Status de admin ' . ($user->is_admin ? 'ativado' : 'desativado') . ' com sucesso!'
        );
    }

    /**
     * Toggle super admin status de um usuário
     */
    public function toggleSuperAdmin(User $user)
    {
        $user->update(['is_super_admin' => !$user->is_super_admin]);
        
        return redirect()->back()->with('success', 
            'Status de super admin ' . ($user->is_super_admin ? 'ativado' : 'desativado') . ' com sucesso!'
        );
    }

    /**
     * Gráfico de receita dos últimos 6 meses
     */
    private function getRevenueChart()
    {
        $months = [];
        $revenues = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M/Y');
            
            $revenue = Payment::whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->approved()
                ->sum('amount');
            
            $revenues[] = (float) $revenue;
        }

        return [
            'labels' => $months,
            'data' => $revenues
        ];
    }

    /**
     * Gráfico de novos usuários dos últimos 6 meses
     */
    private function getUsersChart()
    {
        $months = [];
        $users = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M/Y');
            
            $count = User::whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
            
            $users[] = $count;
        }

        return [
            'labels' => $months,
            'data' => $users
        ];
    }

    /**
     * Planos mais populares
     */
    private function getPopularPlans()
    {
        return Subscription::select('plan_config_id', DB::raw('count(*) as count'))
            ->with('planConfig')
            ->groupBy('plan_config_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Dashboard financeiro
     */
    public function finance()
    {
        $stats = $this->getFinanceStats();
        $revenueChart = $this->getRevenueChart();
        $paymentMethods = $this->getPaymentMethodsStats();
        $subscriptionsByStatus = $this->getSubscriptionsByStatus();
        $monthlyRecurringRevenue = $this->getMonthlyRecurringRevenue();
        $churnRate = $this->getChurnRate();

        return view('admin.finance', compact(
            'stats',
            'revenueChart',
            'paymentMethods',
            'subscriptionsByStatus',
            'monthlyRecurringRevenue',
            'churnRate'
        ));
    }

    /**
     * Estatísticas financeiras
     */
    private function getFinanceStats()
    {
        $totalRevenue = Payment::approved()->sum('amount');
        $monthlyRevenue = Payment::approved()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::active()->count();
        
        $averageTicket = Payment::approved()->avg('amount');
        $conversionRate = $this->getConversionRate();

        return [
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'total_subscriptions' => $totalSubscriptions,
            'active_subscriptions' => $activeSubscriptions,
            'average_ticket' => $averageTicket,
            'conversion_rate' => $conversionRate
        ];
    }

    /**
     * Estatísticas por método de pagamento
     */
    private function getPaymentMethodsStats()
    {
        return Payment::approved()
            ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('payment_method')
            ->orderBy('total', 'desc')
            ->get();
    }

    /**
     * Assinaturas por status
     */
    private function getSubscriptionsByStatus()
    {
        return Subscription::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
    }

    /**
     * MRR (Monthly Recurring Revenue)
     */
    private function getMonthlyRecurringRevenue()
    {
        $mrr = Subscription::active()
            ->with('planConfig')
            ->get()
            ->sum(function ($subscription) {
                return $subscription->billing_cycle === 'monthly' 
                    ? $subscription->amount 
                    : $subscription->amount / 12;
            });

        return $mrr;
    }

    /**
     * Taxa de churn
     */
    private function getChurnRate()
    {
        $totalActive = Subscription::active()->count();
        $cancelledThisMonth = Subscription::where('status', 'cancelled')
            ->whereMonth('cancelled_at', now()->month)
            ->whereYear('cancelled_at', now()->year)
            ->count();

        if ($totalActive === 0) {
            return 0;
        }

        return ($cancelledThisMonth / $totalActive) * 100;
    }

    /**
     * Taxa de conversão (visitors to paid)
     */
    private function getConversionRate()
    {
        $totalUsers = User::count();
        $paidUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('status', 'active');
        })->count();

        if ($totalUsers === 0) {
            return 0;
        }

        return ($paidUsers / $totalUsers) * 100;
    }

    /**
     * Gerenciar planos
     */
    public function plans()
    {
        $plans = \App\Models\PlanConfig::orderBy('sort_order')->orderBy('price_monthly')->get();
        
        return view('admin.plans.index', compact('plans'));
    }

    /**
     * Obter dados de um plano específico (AJAX)
     */
    public function getPlan(\App\Models\PlanConfig $plan)
    {
        return response()->json([
            'success' => true,
            'plan' => $plan
        ]);
    }

    /**
     * Criar novo plano
     */
    public function storePlan(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'plan_name' => 'required|string|max:255|unique:plan_configs,plan_name',
            'display_name' => 'required|string|max:255',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'tokens_limit' => 'required|integer|min:0',
            'documents_limit' => 'required|integer|min:0',
            'margin_percentage' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        try {
            $plan = \App\Models\PlanConfig::create([
                'plan_name' => $request->plan_name,
                'display_name' => $request->display_name,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'tokens_limit' => $request->tokens_limit,
                'documents_limit' => $request->documents_limit,
                'margin_percentage' => $request->margin_percentage,
                'description' => $request->description,
                'features' => $request->features ? json_encode($request->features) : null,
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => \App\Models\PlanConfig::max('sort_order') + 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plano criado com sucesso!',
                'plan' => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar plano: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar plano existente
     */
    public function updatePlan(\Illuminate\Http\Request $request, \App\Models\PlanConfig $plan)
    {
        $request->validate([
            'plan_name' => 'required|string|max:255|unique:plan_configs,plan_name,' . $plan->id,
            'display_name' => 'required|string|max:255',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'tokens_limit' => 'required|integer|min:0',
            'documents_limit' => 'required|integer|min:0',
            'margin_percentage' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        try {
            $plan->update([
                'plan_name' => $request->plan_name,
                'display_name' => $request->display_name,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'tokens_limit' => $request->tokens_limit,
                'documents_limit' => $request->documents_limit,
                'margin_percentage' => $request->margin_percentage,
                'description' => $request->description,
                'features' => $request->features ? json_encode($request->features) : null,
                'is_active' => $request->boolean('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plano atualizado com sucesso!',
                'plan' => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar plano: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ativar/Desativar plano
     */
    public function togglePlan(\App\Models\PlanConfig $plan)
    {
        try {
            $plan->update(['is_active' => !$plan->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Plano ' . ($plan->is_active ? 'ativado' : 'desativado') . ' com sucesso!',
                'is_active' => $plan->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar status do plano: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerenciar provedores de IA
     */
    public function aiProviders()
    {
        $providers = \App\Models\AiProviderConfig::orderBy('sort_order')->orderBy('provider_name')->get();
        
        return view('admin.ai-providers.index', compact('providers'));
    }

    /**
     * API para obter dados dos provedores (AJAX)
     */
    public function getAiProvidersData()
    {
        $providers = \App\Models\AiProviderConfig::orderBy('sort_order')->orderBy('provider_name')->get();
        
        $stats = [
            'total' => $providers->count(),
            'active' => $providers->where('is_active', true)->count(),
            'average_markup' => $providers->avg('base_markup_percentage'),
            'average_cost' => $providers->avg('input_cost_per_1k')
        ];

        return response()->json([
            'success' => true,
            'providers' => $providers,
            'stats' => $stats
        ]);
    }

    /**
     * Obter dados de um provedor específico (AJAX)
     */
    public function getAiProvider(\App\Models\AiProviderConfig $provider)
    {
        return response()->json([
            'success' => true,
            'provider' => $provider
        ]);
    }

    /**
     * Criar novo provedor de IA
     */
    public function storeAiProvider(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'provider_name' => 'required|string|max:255',
            'model_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'input_cost_per_1k' => 'required|numeric|min:0',
            'output_cost_per_1k' => 'required|numeric|min:0',
            'context_length' => 'required|integer|min:1',
            'base_markup_percentage' => 'required|numeric|min:0',
            'min_markup_percentage' => 'required|numeric|min:0',
            'max_markup_percentage' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_default' => 'boolean'
        ]);

        try {
            // Se está marcando como padrão, desmarcar outros
            if ($request->boolean('is_default')) {
                \App\Models\AiProviderConfig::where('provider_name', $request->provider_name)
                    ->update(['is_default' => false]);
            }

            $provider = \App\Models\AiProviderConfig::create([
                'provider_name' => $request->provider_name,
                'model_name' => $request->model_name,
                'display_name' => $request->display_name,
                'input_cost_per_1k' => $request->input_cost_per_1k,
                'output_cost_per_1k' => $request->output_cost_per_1k,
                'context_length' => $request->context_length,
                'base_markup_percentage' => $request->base_markup_percentage,
                'min_markup_percentage' => $request->min_markup_percentage,
                'max_markup_percentage' => $request->max_markup_percentage,
                'is_active' => $request->boolean('is_active', true),
                'is_default' => $request->boolean('is_default', false),
                'sort_order' => $request->sort_order ?? 0,
                'metadata' => []
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Provedor de IA criado com sucesso!',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar provedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar provedor existente
     */
    public function updateAiProvider(\Illuminate\Http\Request $request, \App\Models\AiProviderConfig $provider)
    {
        $request->validate([
            'provider_name' => 'required|string|max:255',
            'model_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'input_cost_per_1k' => 'required|numeric|min:0',
            'output_cost_per_1k' => 'required|numeric|min:0',
            'context_length' => 'required|integer|min:1',
            'base_markup_percentage' => 'required|numeric|min:0',
            'min_markup_percentage' => 'required|numeric|min:0',
            'max_markup_percentage' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_default' => 'boolean'
        ]);

        try {
            // Se está marcando como padrão, desmarcar outros
            if ($request->boolean('is_default')) {
                \App\Models\AiProviderConfig::where('provider_name', $request->provider_name)
                    ->where('id', '!=', $provider->id)
                    ->update(['is_default' => false]);
            }

            $provider->update([
                'provider_name' => $request->provider_name,
                'model_name' => $request->model_name,
                'display_name' => $request->display_name,
                'input_cost_per_1k' => $request->input_cost_per_1k,
                'output_cost_per_1k' => $request->output_cost_per_1k,
                'context_length' => $request->context_length,
                'base_markup_percentage' => $request->base_markup_percentage,
                'min_markup_percentage' => $request->min_markup_percentage,
                'max_markup_percentage' => $request->max_markup_percentage,
                'is_active' => $request->boolean('is_active', true),
                'is_default' => $request->boolean('is_default', false),
                'sort_order' => $request->sort_order ?? $provider->sort_order
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Provedor atualizado com sucesso!',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar provedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ativar/Desativar provedor
     */
    public function toggleAiProvider(\App\Models\AiProviderConfig $provider)
    {
        try {
            $provider->update(['is_active' => !$provider->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Provedor ' . ($provider->is_active ? 'ativado' : 'desativado') . ' com sucesso!',
                'is_active' => $provider->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar status do provedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show document details in admin context
     */
    public function showDocument($id)
    {
        $user = Auth::user();

        // Admin can view any document
        $document = DB::table('documents')->where('id', $id)->first();

        if (!$document) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Document not found');
        }

        // Get chunks
        $chunks = DB::table('chunks')
            ->where('document_id', $id)
            ->orderBy('ord')
            ->get();

        return view('admin.documents.show', compact('user', 'document', 'chunks'));
    }

    /**
     * Download document in admin context
     */
    public function downloadDocument($id)
    {
        $user = Auth::user();

        // Admin can download any document - use raw query to avoid cache issues
        $document = DB::select('SELECT * FROM documents WHERE id = ?', [$id]);
        
        if (empty($document)) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Document not found');
        }
        
        $document = $document[0];

        // Try to find original file using metadata
        $metadata = json_decode($document->metadata ?? '{}', true);
        $filePath = $metadata['file_path'] ?? null;

        if ($filePath && Storage::exists($filePath)) {
            return Storage::download($filePath, $document->title);
        }

        // Try to find file by name pattern - comprehensive search
        $uploadsPath = 'uploads';
        $files = Storage::files($uploadsPath);
        
        // Get document title and clean it for matching
        $documentTitle = $document->title;
        $titleWords = array_filter(explode(' ', $documentTitle), function($word) {
            return strlen(trim($word)) > 2;
        });
        
        // Try multiple matching strategies (prioritize exact matches)
        foreach ($files as $file) {
            $fileName = basename($file);
            $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
            $fileNameWithoutTimestamp = preg_replace('/^\d+_/', '', $fileName);
            $fileBaseNameWithoutTimestamp = pathinfo($fileNameWithoutTimestamp, PATHINFO_FILENAME);
            
            // Additional check: ensure file belongs to the correct tenant
            // Files are stored in tenant-specific directories
            $filePath = dirname($file);
            $tenantFromPath = basename($filePath);
            if ($tenantFromPath !== $document->tenant_slug) {
                continue;
            }
            
            // Strategy 1: Exact title match (highest priority)
            if ($fileNameWithoutTimestamp === $documentTitle) {
                return Storage::download($file, $documentTitle);
            }
            
            // Strategy 2: Title contains file base name (without timestamp)
            if (strpos($documentTitle, $fileBaseNameWithoutTimestamp) !== false) {
                return Storage::download($file, $documentTitle);
            }
            
            // Strategy 3: File base name contains title (without timestamp)
            if (strpos($fileBaseNameWithoutTimestamp, $documentTitle) !== false) {
                return Storage::download($file, $documentTitle);
            }
            
            // Strategy 4: Match by extension and partial content (more specific)
            $docExtension = pathinfo($documentTitle, PATHINFO_EXTENSION);
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            if (!empty($docExtension) && $docExtension === $fileExtension) {
                // Check if any title word appears in filename (avoid generic words)
                $genericWords = ['test', 'document', 'file', 'doc', 'pdf', 'txt', 'csv', 'xlsx', 'docx'];
                foreach ($titleWords as $word) {
                    if (strlen($word) > 3 && !in_array(strtolower($word), $genericWords) && strpos($fileBaseNameWithoutTimestamp, $word) !== false) {
                        return Storage::download($file, $documentTitle);
                    }
                }
            }
        }

        // If no file found, return error with available files info
        return redirect()->route('admin.documents.show', $id)
            ->with('error', 'Arquivo original não encontrado. Apenas o conteúdo extraído está disponível.');
    }

    /**
     * Delete document completely from system
     */
    public function deleteDocument($id)
    {
        $user = Auth::user();

        // Admin can delete any document - use raw query to avoid cache issues
        $document = DB::select('SELECT * FROM documents WHERE id = ?', [$id]);
        
        if (empty($document)) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        $document = $document[0];

        try {
            // Start database transaction
            DB::beginTransaction();

            // 1. Delete all chunks associated with the document
            DB::table('chunks')->where('document_id', $id)->delete();

            // 2. Delete all feedback associated with the document
            DB::table('rag_feedbacks')->where('document_id', $id)->delete();

            // 3. Try to find and delete the original file
            $fileDeleted = false;
            $metadata = json_decode($document->metadata ?? '{}', true);
            $filePath = $metadata['file_path'] ?? null;

            // Try to delete file from metadata path
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
                $fileDeleted = true;
            }

            // Try to find and delete file by name pattern
            if (!$fileDeleted) {
                $uploadsPath = 'uploads';
                $files = Storage::files($uploadsPath);
                
                $documentTitle = $document->title;
                $titleWords = array_filter(explode(' ', $documentTitle), function($word) {
                    return strlen(trim($word)) > 2;
                });
                
                foreach ($files as $file) {
                    $fileName = basename($file);
                    $fileNameWithoutTimestamp = preg_replace('/^\d+_/', '', $fileName);
                    
                    // Try multiple matching strategies
                    if (strpos($fileName, $documentTitle) !== false ||
                        strpos($documentTitle, pathinfo($fileNameWithoutTimestamp, PATHINFO_FILENAME)) !== false) {
                        
                        foreach ($titleWords as $word) {
                            if (strpos($fileName, $word) !== false) {
                                Storage::delete($file);
                                $fileDeleted = true;
                                break 2; // Break out of both loops
                            }
                        }
                    }
                }
            }

            // 4. Delete the document record from database
            DB::table('documents')->where('id', $id)->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento deletado completamente do sistema',
                'document_id' => $id,
                'document_title' => $document->title,
                'file_deleted' => $fileDeleted,
                'space_freed' => true
            ]);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollback();
            
            return response()->json([
                'error' => 'Erro ao deletar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test download method - simplified version
     */
    public function testDownload($id)
    {
        // Direct database query without any caching
        $document = DB::table('documents')->where('id', $id)->first();
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        // Also check with raw query
        $rawDocument = DB::select('SELECT * FROM documents WHERE id = ?', [$id]);
        
        return response()->json([
            'laravel_query' => [
                'id' => $document->id,
                'title' => $document->title,
                'tenant_slug' => $document->tenant_slug,
                'source' => $document->source,
                'uri' => $document->uri
            ],
            'raw_query' => $rawDocument[0] ?? null
        ]);
    }

    /**
     * Página de configurações de pagamento do Mercado Pago
     */
    public function paymentSettings()
    {
        $mercadoPagoConfigs = SystemConfig::where('config_category', 'payment')->get()->keyBy('config_key');
        $plans = PlanConfig::where('is_active', true)->orderBy('price_monthly')->get();
        
        return view('admin.payment.settings', compact('mercadoPagoConfigs', 'plans'));
    }

    /**
     * Atualizar configurações de pagamento
     */
    public function updatePaymentSettings(Request $request)
    {
        $request->validate([
            'mercadopago_access_token' => 'required|string',
            'mercadopago_public_key' => 'required|string',
            'mercadopago_sandbox' => 'boolean',
            'payment_timeout' => 'integer|min:5|max:120'
        ]);

        try {
            // Atualizar configurações do Mercado Pago usando o método set do modelo
            SystemConfig::set('mercadopago_access_token', $request->mercadopago_access_token, 'string', 'payment', 'Access Token do Mercado Pago', true);
            SystemConfig::set('mercadopago_public_key', $request->mercadopago_public_key, 'string', 'payment', 'Public Key do Mercado Pago');
            SystemConfig::set('mercadopago_sandbox', $request->boolean('mercadopago_sandbox'), 'boolean', 'payment', 'Usar sandbox do Mercado Pago');
            SystemConfig::set('payment_timeout', $request->payment_timeout, 'integer', 'payment', 'Timeout de pagamento em minutos');

            return response()->json([
                'success' => true,
                'message' => 'Configurações de pagamento atualizadas com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testar conexão com Mercado Pago
     */
    public function testMercadoPagoConnection()
    {
        try {
            $mercadoPagoService = new MercadoPagoService();
            $testResult = $mercadoPagoService->testConnection();

            if ($testResult) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexão com Mercado Pago estabelecida com sucesso',
                    'data' => [
                        'connection_status' => 'success',
                        'timestamp' => now()->toISOString(),
                        'sandbox_mode' => config('services.mercadopago.sandbox', true)
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha na conexão com Mercado Pago. Verifique suas credenciais.'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todos os pagamentos
     */
    public function payments()
    {
        $payments = Payment::with(['user', 'subscription.planConfig'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.payment.index', compact('payments'));
    }

    /**
     * Ver detalhes de um pagamento específico
     */
    public function paymentDetails($id)
    {
        $payment = Payment::with(['user', 'subscription.planConfig'])->findOrFail($id);
        
        return view('admin.payment.details', compact('payment'));
    }

    /**
     * Atualizar status de pagamento manualmente
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected,cancelled'
        ]);

        try {
            $payment = Payment::findOrFail($id);
            $payment->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Status do pagamento atualizado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de pagamentos
     */
    public function paymentStats()
    {
        $stats = [
            'total_payments' => Payment::count(),
            'approved_payments' => Payment::approved()->count(),
            'pending_payments' => Payment::pending()->count(),
            'rejected_payments' => Payment::rejected()->count(),
            'total_revenue' => Payment::approved()->sum('amount'),
            'monthly_revenue' => Payment::thisMonth()->approved()->sum('amount'),
            'yearly_revenue' => Payment::thisYear()->approved()->sum('amount'),
            'average_payment' => Payment::approved()->avg('amount'),
            'conversion_rate' => Payment::count() > 0 ? 
                round((Payment::approved()->count() / Payment::count()) * 100, 2) : 0
        ];

        // Gráfico de receita dos últimos 12 meses
        $revenueChart = Payment::approved()
            ->selectRaw('DATE_TRUNC(\'month\', created_at) as month, SUM(amount) as total')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Gráfico de pagamentos por status
        $statusChart = Payment::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return view('admin.payment.stats', compact('stats', 'revenueChart', 'statusChart'));
    }
}
