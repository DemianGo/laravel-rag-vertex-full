<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_key',
        'config_name',
        'config_value',
        'config_type',
        'config_category',
        'description',
        'is_encrypted',
        'is_public'
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_public' => 'boolean'
    ];

    /**
     * Obter valor de configuração com cache
     */
    public static function get($key, $default = null)
    {
        return Cache::remember("system_config_{$key}", 3600, function () use ($key, $default) {
            $config = static::where('config_key', $key)->first();
            
            if (!$config) {
                return $default;
            }

            $value = $config->config_value;

            // Se estiver criptografado, descriptografar
            if ($config->is_encrypted) {
                $value = decrypt($value);
            }

            // Converter tipo
            return static::castValue($value, $config->config_type);
        });
    }

    /**
     * Definir valor de configuração
     */
    public static function set($key, $value, $type = 'string', $category = 'general', $description = null, $encrypt = false)
    {
        $config = static::firstOrNew(['config_key' => $key]);
        
        $config->config_name = ucwords(str_replace('_', ' ', $key));
        $config->config_type = $type;
        $config->config_category = $category;
        $config->description = $description;
        $config->is_encrypted = $encrypt;

        // Converter valor para string se necessário
        $stringValue = static::convertToString($value, $type);

        // Criptografar se necessário
        if ($encrypt) {
            $stringValue = encrypt($stringValue);
        }

        $config->config_value = $stringValue;
        $config->save();

        // Limpar cache
        Cache::forget("system_config_{$key}");

        return $config;
    }

    /**
     * Converter valor para o tipo correto
     */
    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
            case 'integer':
                return is_numeric($value) ? (int) $value : 0;
            case 'float':
            case 'decimal':
                return is_numeric($value) ? (float) $value : 0.0;
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }

    /**
     * Converter valor para string
     */
    private static function convertToString($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
                return json_encode($value);
            default:
                return (string) $value;
        }
    }

    /**
     * Obter todas as configurações de uma categoria
     */
    public static function getByCategory($category)
    {
        return static::where('config_category', $category)->get();
    }

    /**
     * Obter configurações públicas
     */
    public static function getPublic()
    {
        return static::where('is_public', true)->get();
    }

    /**
     * Configurações padrão do sistema
     */
    public static function seedDefaults()
    {
        $defaults = [
            // Configurações de IA
            ['key' => 'default_ai_provider', 'value' => 'openai', 'type' => 'string', 'category' => 'ai', 'description' => 'Provedor de IA padrão'],
            ['key' => 'ai_cost_multiplier', 'value' => '1.5', 'type' => 'float', 'category' => 'ai', 'description' => 'Multiplicador de custo base para IA'],
            ['key' => 'max_tokens_per_request', 'value' => '4000', 'type' => 'integer', 'category' => 'ai', 'description' => 'Máximo de tokens por requisição'],
            
            // Configurações de pagamento
            ['key' => 'mercadopago_sandbox', 'value' => 'true', 'type' => 'boolean', 'category' => 'payment', 'description' => 'Usar sandbox do Mercado Pago'],
            ['key' => 'payment_timeout', 'value' => '30', 'type' => 'integer', 'category' => 'payment', 'description' => 'Timeout de pagamento em minutos'],
            
            // Configurações de segurança
            ['key' => 'max_requests_per_minute', 'value' => '60', 'type' => 'integer', 'category' => 'security', 'description' => 'Máximo de requisições por minuto'],
            ['key' => 'enable_api_rate_limit', 'value' => 'true', 'type' => 'boolean', 'category' => 'security', 'description' => 'Habilitar limite de taxa da API'],
            
            // Configurações gerais
            ['key' => 'app_maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'category' => 'general', 'description' => 'Modo de manutenção'],
            ['key' => 'default_currency', 'value' => 'BRL', 'type' => 'string', 'category' => 'general', 'description' => 'Moeda padrão'],
        ];

        foreach ($defaults as $default) {
            static::set(
                $default['key'],
                $default['value'],
                $default['type'],
                $default['category'],
                $default['description']
            );
        }
    }
}