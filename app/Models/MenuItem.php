<?php

namespace App\Models;

use App\Concerns\HasAuditLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasAuditLogs, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'allergens' => 'array',
        'tags' => 'array',
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_items')
            ->withPivot('qty', 'notes')
            ->withTimestamps();
    }

    public function addOns()
    {
        return $this->belongsToMany(AddOn::class);
    }

    public function options()
    {
        return $this->hasMany(MenuOption::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }
}
