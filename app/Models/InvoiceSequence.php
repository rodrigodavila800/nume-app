<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'series',
        'last_number',
    ];

    protected $casts = [
        'last_number' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
