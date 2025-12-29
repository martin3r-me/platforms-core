<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContextFileVariant extends Model
{
    protected $fillable = [
        'context_file_id',
        'variant_type',
        'token',
        'disk',
        'path',
        'width',
        'height',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function contextFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}

