<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promo extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function buyMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'buy_menu_item_id');
    }

    public function getMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'get_menu_item_id');
    }
}
