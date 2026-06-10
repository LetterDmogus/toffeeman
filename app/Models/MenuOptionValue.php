<?php

namespace App\Models;

use Database\Factories\MenuOptionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuOptionValue extends Model
{
    /** @use HasFactory<MenuOptionValueFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'additional_price' => 'decimal:2',
    ];

    public function option()
    {
        return $this->belongsTo(MenuOption::class, 'menu_option_id');
    }
}
