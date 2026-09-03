<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'model_used',
        'summary',
        'marketing_advice',
        'inventory_advice',
        'actionable_steps',
        'raw_metrics',
    ];

    protected function casts(): array
    {
        return [
            'marketing_advice' => 'array',
            'inventory_advice' => 'array',
            'actionable_steps' => 'array',
            'raw_metrics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
