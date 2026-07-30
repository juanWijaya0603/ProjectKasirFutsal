<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sales extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_number',
        'sale_date',
        'total_price',
        'status',
        'payment_method',
        'paid_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function saleItems()
    {
        return $this->hasMany(sale_items::class, 'sale_id');
    }
}