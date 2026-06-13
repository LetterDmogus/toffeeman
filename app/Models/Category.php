<?php

namespace App\Models;

use App\Concerns\HasAuditLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasAuditLogs, SoftDeletes;

    protected $guarded = [];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
