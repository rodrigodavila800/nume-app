<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SofiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sofi_conversation_id',
        'sender',
        'content',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SofiConversation::class, 'sofi_conversation_id');
    }
}
