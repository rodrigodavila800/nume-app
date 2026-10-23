<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\BelongsToTenant;
use App\Notifications\TenantResetPasswordNotification;

class User extends Authenticatable
{
    use HasFactory, Notifiable, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    /**
     * Relacionamento: User pertence a um Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * MantÃ©m o tenant no link para que a redefiniÃ§Ã£o de senha respeite a conta correta.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TenantResetPasswordNotification($token, $this->tenant->slug));
    }

    /**
     * Verifica se o usuário é admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verifica se o usuário é editor
     */
    public function isEditor(): bool
    {
        return in_array($this->role, ['admin', 'editor']);
    }
}
