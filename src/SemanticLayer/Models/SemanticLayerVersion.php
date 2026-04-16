<?php

namespace Platform\Core\SemanticLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

/**
 * Eine versionierte Ausprägung eines SemanticLayers.
 *
 * Enthält die vier Kanäle laut Canvas:
 * perspektive (string), ton (array), heuristiken (array), negativ_raum (array).
 */
class SemanticLayerVersion extends Model
{
    protected $table = 'semantic_layer_versions';

    public $timestamps = false;

    protected $fillable = [
        'semantic_layer_id',
        'semver',
        'version_type',
        'perspektive',
        'ton',
        'heuristiken',
        'negativ_raum',
        'token_count',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'ton' => 'array',
        'heuristiken' => 'array',
        'negativ_raum' => 'array',
        'token_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public const TYPE_MAJOR = 'major';
    public const TYPE_MINOR = 'minor';
    public const TYPE_PATCH = 'patch';

    public function layer(): BelongsTo
    {
        return $this->belongsTo(SemanticLayer::class, 'semantic_layer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payload(): array
    {
        return [
            'perspektive' => $this->perspektive,
            'ton' => $this->ton ?? [],
            'heuristiken' => $this->heuristiken ?? [],
            'negativ_raum' => $this->negativ_raum ?? [],
        ];
    }
}
