<?php

namespace App\Models;

use App\Concerns\HasAuditLogs;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasAuditLogs, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    public function reference()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
