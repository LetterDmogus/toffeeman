<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
        'min_qty' => 'decimal:2',
        'price' => 'decimal:2',
        'small_unit_qty' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batches()
    {
        return $this->hasMany(IngredientBatch::class);
    }

    public function recalculateQty(): void
    {
        $this->qty = $this->batches()->sum('qty');
        $this->save();
    }

    protected static function booted(): void
    {
        static::saving(function (Ingredient $item) {
            if ($item->qty <= 0) {
                $item->status = 'out_of_stock';
            } elseif ($item->qty <= $item->min_qty) {
                $item->status = 'low_stock';
            } else {
                $item->status = 'in_stock';
            }
        });
    }
}
