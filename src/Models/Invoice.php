<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'team_id',
        'number',
        'period_start',
        'period_end',
        'total_net',
        'tax_percent',
        'total_tax',
        'total_gross',
        'status',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_net' => 'float',
        'tax_percent' => 'float',
        'total_tax' => 'float',
        'total_gross' => 'float',
        'meta' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}