<?php

namespace App\Models;

use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasPermissions;

#[Fillable(['name', 'slug', 'description', 'starting_page'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory, HasPermissions, SoftDeletes;

    protected string $guard_name = 'web';

    /**
     * Get the employees for the position.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
