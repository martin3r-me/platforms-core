<?php

namespace Platform\Core\SemanticLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

/**
 * Strukturierter Audit-Eintrag für SemanticLayer-Operationen.
 *
 * Actions: 'created', 'activated', 'enabled_module', 'disabled_module',
 * 'version_created', 'archived', 'schema_rejected'
 *
 * Diff-Format (falls gesetzt):
 * [
 *   ['field' => 'ton', 'op' => 'added', 'from' => null, 'to' => 'klar'],
 *   ['field' => 'perspektive', 'op' => 'changed', 'from' => '...', 'to' => '...'],
 * ]
 */
class SemanticLayerAudit extends Model
{
    protected $table = 'semantic_layer_audit';

    public $timestamps = false;

    protected $fillable = [
        'semantic_layer_id',
        'version_id',
        'action',
        'diff',
        'user_id',
        'context',
        'created_at',
    ];

    protected $casts = [
        'diff' => 'array',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(SemanticLayer::class, 'semantic_layer_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SemanticLayerVersion::class, 'version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function record(
        int $layerId,
        string $action,
        ?int $versionId = null,
        ?array $diff = null,
        ?int $userId = null,
        ?array $context = null,
    ): self {
        return static::create([
            'semantic_layer_id' => $layerId,
            'version_id' => $versionId,
            'action' => $action,
            'diff' => $diff,
            'user_id' => $userId,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}
