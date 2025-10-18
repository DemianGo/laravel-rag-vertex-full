<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PlanConfig;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar conta admin padrão
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@liberai.ai'],
            [
                'name' => 'Administrador LiberAI',
                'password' => Hash::make('admin123456'),
                'is_admin' => true,
                'is_super_admin' => true,
                'admin_permissions' => ['*'], // Todas as permissões
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Conta admin criada:');
        $this->command->info('📧 Email: admin@liberai.ai');
        $this->command->info('🔑 Senha: admin123456');
        $this->command->info('🌐 Acesso: /admin/login');
        $this->command->info('');

        // Criar planos padrão
        $plans = [
            [
                'plan_name' => 'free',
                'display_name' => 'Free Plan',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'tokens_limit' => 100,
                'documents_limit' => 1,
                'features' => [
                    'Basic RAG queries',
                    'PDF extraction',
                    'Community support'
                ],
                'margin_percentage' => 0.00,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Plano gratuito para testes e uso básico'
            ],
            [
                'plan_name' => 'pro',
                'display_name' => 'Pro Plan',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00, // 2 meses grátis
                'tokens_limit' => 10000,
                'documents_limit' => 50,
                'features' => [
                    'Advanced RAG generation',
                    'All extraction methods',
                    'Priority processing',
                    'Email support',
                    'API access',
                    'Video transcription'
                ],
                'margin_percentage' => 30.00,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Plano profissional para uso intensivo'
            ],
            [
                'plan_name' => 'enterprise',
                'display_name' => 'Enterprise Plan',
                'price_monthly' => 99.00,
                'price_yearly' => 990.00, // 2 meses grátis
                'tokens_limit' => 999999,
                'documents_limit' => 999999,
                'features' => [
                    'Unlimited usage',
                    'Admin panel access',
                    'Custom deployment',
                    'Priority support',
                    'Advanced analytics',
                    'Webhook integrations',
                    'White-label options'
                ],
                'margin_percentage' => 40.00,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'Plano enterprise com recursos ilimitados'
            ]
        ];

        foreach ($plans as $planData) {
            PlanConfig::firstOrCreate(
                ['plan_name' => $planData['plan_name']],
                $planData
            );
        }

        $this->command->info('✅ Planos configurados:');
        $this->command->info('🆓 Free: R$ 0,00/mês - 100 tokens, 1 documento');
        $this->command->info('💼 Pro: R$ 29,00/mês - 10.000 tokens, 50 documentos');
        $this->command->info('🏢 Enterprise: R$ 99,00/mês - Ilimitado');
        $this->command->info('');

        // Atualizar usuários existentes para usar planos configuráveis
        $users = User::whereDoesntHave('userPlan')->get();
        foreach ($users as $user) {
            $plan = PlanConfig::where('plan_name', $user->plan ?? 'free')->first();
            if ($plan) {
                $user->update([
                    'tokens_limit' => $plan->tokens_limit,
                    'documents_limit' => $plan->documents_limit,
                ]);
            }
        }

        $this->command->info('✅ Usuários existentes atualizados com novos limites');
        $this->command->info('');
        $this->command->info('🎉 Área administrativa configurada com sucesso!');
        $this->command->info('📊 Acesse: http://localhost:8000/admin');
    }
}
