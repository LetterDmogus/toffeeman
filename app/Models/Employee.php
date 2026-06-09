<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'salary', 'status', 'hired_at'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $appends = ['position_id', 'position'];

    /**
     * Get the user associated with the employee.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the position ID from the user.
     */
    public function getPositionIdAttribute(): ?int
    {
        return $this->user?->position_id;
    }

    /**
     * Get the position from the user.
     */
    public function getPositionAttribute(): ?Position
    {
        return $this->user?->position;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'hired_at' => 'date',
        ];
    }
}
