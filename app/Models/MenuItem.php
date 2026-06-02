<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use SoftDeletes;

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
}
