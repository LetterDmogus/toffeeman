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
        'purchase_date' => 'date',
        'qty_good' => 'decimal:2',
        'qty_fair' => 'decimal:2',
        'qty_damaged' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryItem $item) {
            $item->qty = ($item->qty_good ?? 0) + ($item->qty_fair ?? 0) + ($item->qty_damaged ?? 0);

            if ($item->qty <= 0) {
                $item->status = 'out_of_stock';
            } elseif ($item->qty <= $item->min_qty) {
                $item->status = 'low_stock';
            } else {
                $item->status = 'in_stock';
            }
        });

        static::saved(function (InventoryItem $item) {
            if ($item->qty <= 0) {
                MenuItem::where('inventory_item_id', $item->id)->update(['status' => 'sold_out']);
            }
        });
    }
}
