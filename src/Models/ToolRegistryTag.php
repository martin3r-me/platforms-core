<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolRegistryTag extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'tool_registry_tags';

    protected $fillable = [
        'tool_registry_entry_id',
        'tag',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ToolRegistryEntry::class, 'tool_registry_entry_id');
    }
}
