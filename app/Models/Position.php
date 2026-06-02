<?php

namespace App\Models;

use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the employees for the position.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
