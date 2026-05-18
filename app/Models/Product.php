<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';
    
    protected $fillable = [
        'id', 'user_id', 'name', 'description', 'sku', 'quantity', 
        'min_stock_level', 'unit_price', 'cost_price', 'currency', 
        'image_path', 'barcode', 'quantity_sold', 'revenue_generated',
        'last_sold_at', 'last_alert_sent', 'created_at', 'updated_at', 'deleted_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'min_stock_level' => 'integer',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'quantity_sold' => 'integer',
        'revenue_generated' => 'decimal:2',
        'last_sold_at' => 'datetime',
        'last_alert_sent' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function isLowStock()
    {
        return $this->quantity <= $this->min_stock_level;
    }

    public function increaseStock($quantity)
    {
        $this->quantity += $quantity;
        $this->save();
        return $this;
    }

    public function decreaseStock($quantity)
    {
        if ($this->quantity >= $quantity) {
            $this->quantity -= $quantity;
            $this->quantity_sold += $quantity;
            $this->revenue_generated += $quantity * $this->unit_price;
            $this->last_sold_at = now();
            $this->save();
            return true;
        }
        return false;
    }

    public function calculateProfit()
    {
        if ($this->cost_price) {
            return $this->unit_price - $this->cost_price;
        }
        return null;
    }

    public function getProfitMargin()
    {
        if ($this->cost_price && $this->unit_price > 0) {
            return round((($this->unit_price - $this->cost_price) / $this->unit_price) * 100, 2);
        }
        return null;
    }
}