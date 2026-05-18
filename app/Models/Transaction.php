<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    protected $table = 'transactions';
    protected $fillable = [
        'id','user_id','category_id','type','amount','description','transaction_date',
        'receipt_path','currency','exchange_rate','is_recurring','recurring_parent_id',
        'customer_name','supplier_id','invoice_id','mpesa_receipt','qr_code_id',
        'is_verified','verified_by','verified_at','notes'
    ];
    protected $casts = [
        'transaction_date'=>'date','amount'=>'decimal:2','exchange_rate'=>'decimal:6',
        'is_recurring'=>'boolean','is_verified'=>'boolean','verified_at'=>'datetime'
    ];

    public function user(){ return $this->belongsTo(User::class); }
    public function category(){ return $this->belongsTo(Category::class); }
    public function supplier(){ return $this->belongsTo(Supplier::class); }
    public function invoice(){ return $this->belongsTo(Invoice::class); }
    public function receipts(){ return $this->hasMany(Receipt::class); }
    public function journalEntry(){ return $this->hasOne(JournalEntry::class); }

    public function scopeIncome($q){ return $q->where('type','income'); }
    public function scopeExpense($q){ return $q->where('type','expense'); }
    public function scopeThisMonth($q){ return $q->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year); }
}