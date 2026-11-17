<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ausstehender Direkt-Upload (core.context.files.upload.request → PUT signierter Link → upload.complete).
 * Die Bytes liegen direkt im Storage der ContextFile-Logik (S3), nie auf dem App-Server.
 */
class ContextFileUpload extends Model
{
    protected $fillable = [
        'uuid', 'team_id', 'user_id', 'context_type', 'context_id', 'file_name', 'mime_type',
        'total_size', 'sha256', 'disk', 'path', 'status', 'expires_at',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'expires_at' => 'datetime',
    ];
}
