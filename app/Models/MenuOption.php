<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuOption extends Model
{
    /** @use HasFactory<\Database\Factories\MenuOptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function values()
    {
        return $this->hasMany(MenuOptionValue::class);
    }
}
