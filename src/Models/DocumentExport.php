<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExport extends Model
{
    protected $fillable = [
        'document_id',
        'exported_by_user_id',
        'renderer',
        'output_context_file_id',
        'status',
        'error_message',
        'renderer_options',
        'meta',
    ];

    protected $casts = [
        'renderer_options' => 'array',
        'meta' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function exportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }

    public function outputFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class, 'output_context_file_id');
    }
}
