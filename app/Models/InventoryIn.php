<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryIn extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(InventoryInDetail::class, 'inventory_in_id');
    }

    public function attachments()
    {
        return $this->morphMany(InventoryAttachment::class, 'attachable');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
