<?php

namespace App\Models;

use App\Concerns\HasAuditLogs;
use Database\Factories\AddOnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddOn extends Model
{
    /** @use HasFactory<AddOnFactory> */
    use HasAuditLogs, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class);
    }
}
