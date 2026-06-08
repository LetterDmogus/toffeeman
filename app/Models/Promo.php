<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'condition_value' => 'decimal:2',
        'reward_value' => 'decimal:2',
        'schedule_days' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * The menu item that triggers the condition (for specific_item_qty).
     */
    public function conditionMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'condition_menu_item_id');
    }

    /**
     * The free menu item rewarded (for free_item reward).
     */
    public function rewardMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'reward_menu_item_id');
    }

    /**
     * Specific menu items this promo's reward applies to (when reward_scope = 'specific').
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'promo_items');
    }
}
