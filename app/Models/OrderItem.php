<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'variants' => 'array',
        'add_ons' => 'array',
        'package_items' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (OrderItem $item) {
            $item->deductStock();
        });

        static::deleted(function (OrderItem $item) {
            if ($item->order && $item->order->status !== 'cancelled') {
                $item->restoreStock();
            }
        });
    }

    public function deductStock(): void
    {
        // 1. Standalone MenuItem
        if ($this->menuItem && $this->menuItem->inventory_item_id) {
            $inventoryItem = $this->menuItem->inventoryItem;
            if ($inventoryItem) {
                $inventoryItem->decrement('qty_good', $this->qty);
                $inventoryItem->save();
            }
        }

        // 2. Package Items containing inventory items
        if ($this->package) {
            $packageItems = $this->package->packageItems()->whereNotNull('inventory_item_id')->get();
            foreach ($packageItems as $pkgItem) {
                $inventoryItem = $pkgItem->inventoryItem;
                if ($inventoryItem) {
                    $inventoryItem->decrement('qty_good', $this->qty * $pkgItem->qty);
                    $inventoryItem->save();
                }
            }
        }
    }

    public function restoreStock(): void
    {
        // 1. Standalone MenuItem
        if ($this->menuItem && $this->menuItem->inventory_item_id) {
            $inventoryItem = $this->menuItem->inventoryItem;
            if ($inventoryItem) {
                $inventoryItem->increment('qty_good', $this->qty);
                $inventoryItem->save();
            }
        }

        // 2. Package Items containing inventory items
        if ($this->package) {
            $packageItems = $this->package->packageItems()->whereNotNull('inventory_item_id')->get();
            foreach ($packageItems as $pkgItem) {
                $inventoryItem = $pkgItem->inventoryItem;
                if ($inventoryItem) {
                    $inventoryItem->increment('qty_good', $this->qty * $pkgItem->qty);
                    $inventoryItem->save();
                }
            }
        }
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
