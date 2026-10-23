<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Str;

class Production extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'cliente_id',
        'pack_purchase_id',
        'created_by',
        'title',
        'description',
        'status',
        'target_publication_date',
        'seo_keywords',
    ];

    protected function casts(): array
    {
        return [
            'target_publication_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Production $production) {
            if (empty($production->uuid)) {
                $production->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Relacionamento: Production pertence a um User (criador)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relacionamento: Production pertence a um Cliente (comprador do pacote)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relacionamento: Production consome de uma compra de pacote
     */
    public function packPurchase(): BelongsTo
    {
        return $this->belongsTo(PackPurchase::class);
    }

    /**
     * Relacionamento: Production possui muitos Roteiros
     */
    public function roteiros(): HasMany
    {
        return $this->hasMany(Roteiro::class);
    }

    /**
     * Relacionamento: Production possui muitos Videos
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * Obtém o roteiro aprovado da production
     */
    public function approvedRoteiro(): HasMany
    {
        return $this->roteiros()->where('status', 'approved');
    }

    /**
     * Verifica se production está pronta para produção
     */
    public function isReadyForProduction(): bool
    {
        return $this->status === 'approved' && $this->roteiros()
            ->where('status', 'approved')
            ->exists();
    }
}
