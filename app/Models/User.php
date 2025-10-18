<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Subscription;
use App\Models\Payment;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_super_admin',
        'admin_permissions',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'api_key_created_at' => 'datetime',
            'api_key_last_used_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'admin_permissions' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Generate a new API key for the user.
     */
    public function generateApiKey(): string
    {
        $apiKey = 'rag_' . bin2hex(random_bytes(28)); // 56 hex chars + prefix = 60 chars
        
        $this->api_key = $apiKey;
        $this->api_key_created_at = now();
        $this->api_key_last_used_at = null;
        $this->save();

        return $apiKey;
    }

    /**
     * Regenerate the API key.
     */
    public function regenerateApiKey(): string
    {
        return $this->generateApiKey();
    }

    /**
     * Update the last used timestamp for the API key.
     */
    public function touchApiKey(): void
    {
        $this->api_key_last_used_at = now();
        $this->save();
    }

    /**
     * Check if user has an API key.
     */
    public function hasApiKey(): bool
    {
        return !is_null($this->api_key);
    }

    /**
     * Get masked API key for display (show first 12 and last 4 characters).
     */
    public function getMaskedApiKeyAttribute(): ?string
    {
        if (!$this->api_key) {
            return null;
        }

        $key = $this->api_key;
        $length = strlen($key);
        
        if ($length <= 16) {
            return substr($key, 0, 8) . '...';
        }

        return substr($key, 0, 12) . '...' . substr($key, -4);
    }

    /**
     * Relationship with UserPlan.
     */
    public function userPlan()
    {
        return $this->hasOne(\App\Models\UserPlan::class);
    }

    /**
     * Relacionamentos admin
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getDocuments()
    {
        // Busca documentos do usuário usando tenant_slug
        return Document::where('tenant_slug', $this->email)
            ->orWhere('tenant_slug', 'user_' . $this->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Verifica se o usuário é admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin || $this->is_super_admin;
    }

    /**
     * Verifica se o usuário é super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public function hasAdminPermission(string $permission): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->is_admin && in_array($permission, $this->admin_permissions ?? []);
    }

    /**
     * Retorna a assinatura ativa do usuário
     */
    public function activeSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();
    }

    /**
     * Atualiza último login do usuário
     */
    public function updateLastLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }
}
