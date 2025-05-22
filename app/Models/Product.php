<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'code', 
        'name', 
        'description', 
        'price', 
        'stock', 
        'unit', 
        'image'
    ];

    public function stockCards()
    {
        return $this->hasMany(StockCard::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
}
