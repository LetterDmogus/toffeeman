<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryOut extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(InventoryOutDetail::class, 'inventory_out_id');
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
