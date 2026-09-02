<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PackingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_sn',
        'tracking_number',
        'shopee_order_id',
        'status',
        'packer_name',
        'items_checked',
        'video_path',
        'video_duration',
        'file_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'items_checked' => 'array',
            'video_duration' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function shopeeOrder(): BelongsTo
    {
        return $this->belongsTo(ShopeeOrder::class);
    }

    public function getVideoUrlAttribute(): ?string
    {
        if ($this->video_path) {
            return Storage::disk('public')->url($this->video_path);
        }

        return null;
    }

    public function getFormattedDurationAttribute(): string
    {
        if (! $this->video_duration) {
            return '00:00';
        }
        $minutes = floor($this->video_duration / 60);
        $seconds = $this->video_duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
