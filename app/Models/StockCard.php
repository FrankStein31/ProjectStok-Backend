<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCard extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'product_id',
        'initial_stock',
        'in_stock',
        'out_stock',
        'final_stock',
        'date',
        'notes'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
