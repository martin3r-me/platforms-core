<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'label',
        'billable_type',
        'billable_model',
        'count',
        'unit_price',
        'total',
        'details',
    ];

    protected $casts = [
        'count' => 'integer',
        'unit_price' => 'float',
        'total' => 'float',
        'details' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}