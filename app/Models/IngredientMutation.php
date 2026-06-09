<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientMutation extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IngredientBatch::class, 'ingredient_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the owning reference model.
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
