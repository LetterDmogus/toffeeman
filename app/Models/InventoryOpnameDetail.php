<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryOpnameDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty_good_system' => 'decimal:2',
        'qty_good_physical' => 'decimal:2',
        'qty_fair_system' => 'decimal:2',
        'qty_fair_physical' => 'decimal:2',
        'qty_damaged_system' => 'decimal:2',
        'qty_damaged_physical' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(InventoryOpname::class, 'inventory_opname_id');
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryOpnameDetail $detail) {
            $item = $detail->item;
            if ($item) {
                $detail->qty_good_system = $item->qty_good;
                $detail->qty_fair_system = $item->qty_fair;
                $detail->qty_damaged_system = $item->qty_damaged;
            }
        });

        static::created(function (InventoryOpnameDetail $detail) {
            $item = $detail->item;
            if ($item) {
                $item->qty_good = $detail->qty_good_physical;
                $item->qty_fair = $detail->qty_fair_physical;
                $item->qty_damaged = $detail->qty_damaged_physical;
                $item->save();
            }
        });

        static::deleted(function (InventoryOpnameDetail $detail) {
            $item = $detail->item;
            if ($item) {
                $item->qty_good = $detail->qty_good_system;
                $item->qty_fair = $detail->qty_fair_system;
                $item->qty_damaged = $detail->qty_damaged_system;
                $item->save();
            }
        });
    }
}
