<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

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
}
