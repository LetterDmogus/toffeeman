<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
        'min_qty' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }
}
