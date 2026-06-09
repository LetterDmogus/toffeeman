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
        'conversion_factor' => 'decimal:2',
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
        $rawSum = $this->batches()
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', now()->toDateString());
            })
            ->sum('qty');

        $this->attributes['qty'] = $rawSum;
        $this->attributes['small_unit_qty'] = $rawSum;
        $this->save();
    }

    // Accessor to display quantity in main unit (e.g. kg)
    public function getQtyAttribute($value)
    {
        $factor = (float) ($this->conversion_factor ?? 1.0);

        return $value / $factor;
    }

    // Mutator to store quantity in base/small unit (e.g. gram)
    public function setQtyAttribute($value)
    {
        $factor = (float) ($this->conversion_factor ?? 1.0);
        $this->attributes['qty'] = $value * $factor;
    }

    // Accessor for min_qty in main unit
    public function getMinQtyAttribute($value)
    {
        $factor = (float) ($this->conversion_factor ?? 1.0);

        return $value / $factor;
    }

    // Mutator for min_qty to store in base/small unit
    public function setMinQtyAttribute($value)
    {
        $factor = (float) ($this->conversion_factor ?? 1.0);
        $this->attributes['min_qty'] = $value * $factor;
    }

    // Accessor for small_unit_qty (returns the raw base quantity, e.g. grams)
    public function getSmallUnitQtyAttribute()
    {
        return (float) ($this->attributes['qty'] ?? 0);
    }

    // Mutator for small_unit_qty
    public function setSmallUnitQtyAttribute($value)
    {
        $this->attributes['small_unit_qty'] = $value;
    }

    protected static function booted(): void
    {
        static::saving(function (Ingredient $item) {
            // Check status based on the display value comparison
            $qty = $item->qty;
            $minQty = $item->min_qty;

            if ($qty <= 0) {
                $item->status = 'out_of_stock';
            } elseif ($qty <= $minQty) {
                $item->status = 'low_stock';
            } else {
                $item->status = 'in_stock';
            }
        });
    }
}
