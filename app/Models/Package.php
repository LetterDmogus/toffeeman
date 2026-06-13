<?php

namespace App\Models;

use App\Concerns\HasAuditLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasAuditLogs, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'package_items')
            ->withPivot('qty', 'notes')
            ->withTimestamps();
    }

    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'package_items')
            ->withPivot('qty', 'notes')
            ->withTimestamps();
    }

    public function packageItems()
    {
        return $this->hasMany(PackageItem::class);
    }
}
