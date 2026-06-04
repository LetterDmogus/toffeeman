<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'payment_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updated(function (Order $order) {
            // Revert stock if order is cancelled
            if ($order->isDirty('status') && $order->status === 'cancelled') {
                foreach ($order->items as $item) {
                    $item->restoreStock();
                }
            }

            // Deduct stock if order was cancelled and now restored
            if ($order->isDirty('status') && $order->getOriginal('status') === 'cancelled' && $order->status !== 'cancelled') {
                foreach ($order->items as $item) {
                    $item->deductStock();
                }
            }
        });
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'reference');
    }
}
