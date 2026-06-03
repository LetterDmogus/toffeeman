<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientBatch extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::saved(function (IngredientBatch $batch) {
            $batch->ingredient?->recalculateQty();
        });

        static::deleted(function (IngredientBatch $batch) {
            $batch->ingredient?->recalculateQty();
        });
    }
}
