<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasEmbeddableVideo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowcaseVideo extends Model
{
    use BelongsToTenant, HasFactory, HasEmbeddableVideo;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'is_vertical',
        'category',
        'sort_order',
        'is_featured',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_vertical' => 'boolean',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getCategoryNameAttribute(): string
    {
        if ($this->category_id && $this->relationLoaded('category') && $this->getRelation('category')) {
            return $this->getRelation('category')->name;
        }
        if ($this->category_id) {
            $cat = $this->category()->first();
            if ($cat) {
                return $cat->name;
            }
        }
        $legacyCategory = $this->attributes['category'] ?? null;
        if (is_string($legacyCategory) && ! empty($legacyCategory)) {
            return $legacyCategory;
        }

        return 'Produção';
    }

    public function getTranslatedTitleAttribute(): string
    {
        return __($this->title);
    }

    public function getTranslatedDescriptionAttribute(): ?string
    {
        return $this->description ? __($this->description) : null;
    }
}
