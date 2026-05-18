<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'suppliers';
    
    protected $fillable = [
        'id', 'user_id', 'name', 'contact_person', 'phone', 'email', 
        'address', 'tax_pin', 'payment_terms', 'notes', 'created_at', 
        'updated_at', 'deleted_at'
    ];

    protected $casts = [
        'deleted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'supplier_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'supplier_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id', 'id');
    }

    public function getTotalPurchased()
    {
        return $this->transactions()->where('type', 'expense')->sum('amount');
    }

    public function getOutstandingBalance()
    {
        $total = $this->getTotalPurchased();
        $paid = $this->payments()->sum('amount');
        return $total - $paid;
    }
}