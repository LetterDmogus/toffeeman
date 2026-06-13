<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class IngredientBatch extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
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

    // Accessor to display batch quantity in main unit (e.g. kg)
    public function getQtyAttribute($value)
    {
        $factor = 1.0;
        if ($this->ingredient) {
            $factor = (float) $this->ingredient->conversion_factor;
        }

        return $value / $factor;
    }

    // Mutator to store batch quantity in base/small unit (e.g. gram)
    public function setQtyAttribute($value)
    {
        $factor = 1.0;
        $ingredientId = $this->ingredient_id ?? $this->attributes['ingredient_id'] ?? null;
        if ($ingredientId) {
            $ingredient = Ingredient::find($ingredientId);
            if ($ingredient) {
                $factor = (float) $ingredient->conversion_factor;
            }
        }
        $this->attributes['qty'] = $value * $factor;
    }

    protected static function booted(): void
    {
        static::created(function (IngredientBatch $batch) {
            $batch->ingredient?->recalculateQty();

            // Log stock mutation 'in'
            IngredientMutation::create([
                'ingredient_id' => $batch->ingredient_id,
                'ingredient_batch_id' => $batch->id,
                'type' => 'in',
                'qty' => $batch->getRawOriginal('qty') ?? $batch->getAttributes()['qty'] ?? 0,
                'reference_type' => IngredientBatch::class,
                'reference_id' => $batch->id,
                'notes' => 'Penambahan batch baru: '.$batch->batch_number,
                'created_by' => $batch->created_by ?? auth()->id(),
            ]);

            // Log financial transaction expense
            $amount = (float) ($batch->price ?? 0);

            if ($amount > 0) {
                Transaction::create([
                    'transaction_number' => 'TRX-'.strtoupper(Str::random(10)),
                    'type' => 'expense',
                    'category' => 'ingredient_purchase',
                    'reference_type' => IngredientBatch::class,
                    'reference_id' => $batch->id,
                    'user_id' => $batch->created_by ?? auth()->id(),
                    'amount' => $amount,
                    'payment_method' => 'cash',
                    'payment_status' => 'completed',
                    'description' => 'Pembelian bahan: '.($batch->ingredient?->name ?? 'Bahan Dapur').' (Batch: '.$batch->batch_number.')',
                    'transaction_date' => now(),
                ]);
            }
        });

        static::saved(function (IngredientBatch $batch) {
            $batch->ingredient?->recalculateQty();
        });

        static::deleted(function (IngredientBatch $batch) {
            $batch->ingredient?->recalculateQty();

            // Log stock mutation 'out'
            IngredientMutation::create([
                'ingredient_id' => $batch->ingredient_id,
                'ingredient_batch_id' => $batch->id,
                'type' => 'out',
                'qty' => $batch->getRawOriginal('qty') ?? $batch->getAttributes()['qty'] ?? 0,
                'reference_type' => IngredientBatch::class,
                'reference_id' => $batch->id,
                'notes' => 'Hapus/buang batch: '.$batch->batch_number,
                'created_by' => auth()->id(),
            ]);

            // Delete associated transactions
            Transaction::where('reference_type', IngredientBatch::class)
                ->where('reference_id', $batch->id)
                ->delete();
        });
    }
}
