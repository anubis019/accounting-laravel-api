<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $table = 'invoices';
    
    protected $fillable = [
        'id', 'user_id', 'customer_id', 'invoice_number', 'issue_date',
        'due_date', 'subtotal', 'tax', 'total', 'status', 'notes',
        'pdf_path', 'created_at', 'updated_at', 'deleted_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'deleted_at' => 'datetime'
    ];

    const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id', 'id');
    }

    public function isOverdue()
    {
        return $this->due_date < now() && $this->status === 'sent';
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->save();
        
        // Create transaction for payment
        Transaction::create([
            'user_id' => $this->user_id,
            'type' => 'income',
            'amount' => $this->total,
            'description' => "Payment for invoice {$this->invoice_number}",
            'transaction_date' => now(),
            'invoice_id' => $this->id,
            'customer_name' => $this->customer->name
        ]);
        
        return $this;
    }

    public function getAmountPaid()
    {
        return $this->transactions()->sum('amount');
    }

    public function getRemainingBalance()
    {
        return $this->total - $this->getAmountPaid();
    }
}