<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolRegistryRequires extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'tool_registry_requires';

    protected $fillable = [
        'tool_registry_entry_id',
        'required_tool_name',
        'for_param',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ToolRegistryEntry::class, 'tool_registry_entry_id');
    }
}
