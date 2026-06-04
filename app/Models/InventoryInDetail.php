<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryInDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty_good' => 'decimal:2',
        'qty_fair' => 'decimal:2',
        'qty_damaged' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(InventoryIn::class, 'inventory_in_id');
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    protected static function booted(): void
    {
        static::created(function (InventoryInDetail $detail) {
            $item = $detail->item;
            if ($item) {
                $item->increment('qty_good', $detail->qty_good);
                $item->increment('qty_fair', $detail->qty_fair);
                $item->increment('qty_damaged', $detail->qty_damaged);
                $item->save();
            }
        });

        static::deleted(function (InventoryInDetail $detail) {
            $item = $detail->item;
            if ($item) {
                $item->decrement('qty_good', $detail->qty_good);
                $item->decrement('qty_fair', $detail->qty_fair);
                $item->decrement('qty_damaged', $detail->qty_damaged);
                $item->save();
            }
        });
    }
}
