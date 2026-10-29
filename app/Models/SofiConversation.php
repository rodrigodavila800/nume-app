<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SofiConversation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'production_id',
        'title',
        'status',
        'channel',
        'contact_phone',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SofiMessage::class)->orderBy('id', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(SofiMessage::class)->latestOfMany();
    }
}
