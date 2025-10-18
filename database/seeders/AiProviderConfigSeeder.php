<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiProviderConfig;
use App\Models\SystemConfig;

class AiProviderConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Configurações padrão do sistema
        SystemConfig::seedDefaults();

        // Configurações de provedores de IA (valores em USD por 1K tokens)
        $providers = [
            // OpenAI
            [
                'provider_name' => 'openai',
                'model_name' => 'gpt-4',
                'display_name' => 'GPT-4',
                'input_cost_per_1k' => 0.03, // $0.03 por 1K tokens entrada
                'output_cost_per_1k' => 0.06, // $0.06 por 1K tokens saída
                'context_length' => 8192,
                'base_markup_percentage' => 50.0, // 50% de margem
                'min_markup_percentage' => 20.0,
                'max_markup_percentage' => 200.0,
                'is_default' => true,
                'sort_order' => 1,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => true
                ]
            ],
            [
                'provider_name' => 'openai',
                'model_name' => 'gpt-4-turbo',
                'display_name' => 'GPT-4 Turbo',
                'input_cost_per_1k' => 0.01,
                'output_cost_per_1k' => 0.03,
                'context_length' => 128000,
                'base_markup_percentage' => 60.0,
                'min_markup_percentage' => 25.0,
                'max_markup_percentage' => 200.0,
                'is_default' => false,
                'sort_order' => 2,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => true
                ]
            ],
            [
                'provider_name' => 'openai',
                'model_name' => 'gpt-3.5-turbo',
                'display_name' => 'GPT-3.5 Turbo',
                'input_cost_per_1k' => 0.001,
                'output_cost_per_1k' => 0.002,
                'context_length' => 4096,
                'base_markup_percentage' => 100.0, // 100% de margem (modelo mais barato)
                'min_markup_percentage' => 50.0,
                'max_markup_percentage' => 300.0,
                'is_default' => false,
                'sort_order' => 3,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => false
                ]
            ],

            // Google Gemini
            [
                'provider_name' => 'gemini',
                'model_name' => 'gemini-pro',
                'display_name' => 'Gemini Pro',
                'input_cost_per_1k' => 0.0005,
                'output_cost_per_1k' => 0.0015,
                'context_length' => 30720,
                'base_markup_percentage' => 80.0,
                'min_markup_percentage' => 30.0,
                'max_markup_percentage' => 250.0,
                'is_default' => false,
                'sort_order' => 4,
                'metadata' => [
                    'max_tokens' => 2048,
                    'temperature' => 0.7,
                    'supports_functions' => false
                ]
            ],
            [
                'provider_name' => 'gemini',
                'model_name' => 'gemini-pro-vision',
                'display_name' => 'Gemini Pro Vision',
                'input_cost_per_1k' => 0.0005,
                'output_cost_per_1k' => 0.0015,
                'context_length' => 12288,
                'base_markup_percentage' => 90.0,
                'min_markup_percentage' => 40.0,
                'max_markup_percentage' => 250.0,
                'is_default' => false,
                'sort_order' => 5,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => false,
                    'supports_images' => true
                ]
            ],

            // Anthropic Claude
            [
                'provider_name' => 'claude',
                'model_name' => 'claude-3-opus',
                'display_name' => 'Claude 3 Opus',
                'input_cost_per_1k' => 0.015,
                'output_cost_per_1k' => 0.075,
                'context_length' => 200000,
                'base_markup_percentage' => 45.0,
                'min_markup_percentage' => 20.0,
                'max_markup_percentage' => 150.0,
                'is_default' => false,
                'sort_order' => 6,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => false
                ]
            ],
            [
                'provider_name' => 'claude',
                'model_name' => 'claude-3-sonnet',
                'display_name' => 'Claude 3 Sonnet',
                'input_cost_per_1k' => 0.003,
                'output_cost_per_1k' => 0.015,
                'context_length' => 200000,
                'base_markup_percentage' => 55.0,
                'min_markup_percentage' => 25.0,
                'max_markup_percentage' => 180.0,
                'is_default' => false,
                'sort_order' => 7,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => false
                ]
            ],
            [
                'provider_name' => 'claude',
                'model_name' => 'claude-3-haiku',
                'display_name' => 'Claude 3 Haiku',
                'input_cost_per_1k' => 0.00025,
                'output_cost_per_1k' => 0.00125,
                'context_length' => 200000,
                'base_markup_percentage' => 70.0,
                'min_markup_percentage' => 30.0,
                'max_markup_percentage' => 200.0,
                'is_default' => false,
                'sort_order' => 8,
                'metadata' => [
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'supports_functions' => false
                ]
            ]
        ];

        foreach ($providers as $provider) {
            AiProviderConfig::updateOrCreate(
                [
                    'provider_name' => $provider['provider_name'],
                    'model_name' => $provider['model_name']
                ],
                $provider
            );
        }

        $this->command->info('✅ Configurações de IA criadas:');
        $this->command->info('🤖 OpenAI: GPT-4, GPT-4 Turbo, GPT-3.5 Turbo');
        $this->command->info('🧠 Google: Gemini Pro, Gemini Pro Vision');
        $this->command->info('🎭 Anthropic: Claude 3 Opus, Sonnet, Haiku');
        $this->command->info('💰 Margens configuradas: 20% - 300%');
        $this->command->info('🔧 Configurações do sistema inicializadas');
    }
}