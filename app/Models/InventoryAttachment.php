<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAttachment extends Model
{
    protected $guarded = [];

    protected $appends = ['url'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function getUrlAttribute()
    {
        if (! $this->file_path) {
            return null;
        }

        // Support both old paths starting with 'public/' and new paths stored relative to 'public' disk
        $path = str_starts_with($this->file_path, 'public/')
            ? substr($this->file_path, 7)
            : $this->file_path;

        return asset(\Storage::disk('public')->url($path));
    }
}
