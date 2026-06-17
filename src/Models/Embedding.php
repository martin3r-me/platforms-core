<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Embedding extends Model
{
    protected $table = 'core_embeddings';

    protected $fillable = [
        'team_id',
        'entity_type',
        'entity_id',
        'provider',
        'model',
        'dimensions',
        'vector',
        'metadata',
        'source_hash',
    ];

    protected $casts = [
        'team_id' => 'int',
        'dimensions' => 'int',
        'vector' => 'array',
        'metadata' => 'array',
    ];
}
