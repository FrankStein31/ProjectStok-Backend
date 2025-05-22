<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'before_stock',
        'after_stock',
        'date',
        'description',
        'user_id'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
