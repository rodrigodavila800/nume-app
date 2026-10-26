<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_plan_id',
        'production_id',
        'video_type_id',
        'week_number',
        'funnel_stage',
        'scheduled_day_time',
        'topic',
        'hook_text',
        'seo_caption',
        'hashtags',
        'format',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hashtags' => 'array',
            'week_number' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketingPlan::class, 'marketing_plan_id');
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function videoType(): BelongsTo
    {
        return $this->belongsTo(VideoType::class);
    }
}
