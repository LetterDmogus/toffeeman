<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageItem extends Model
{
    protected $guarded = [];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
