<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use SoftDeletes;

    protected $table = 'budgets';
    
    protected $fillable = [
        'id', 'user_id', 'category_id', 'amount', 'period', 
        'start_date', 'end_date', 'created_at', 'updated_at', 'deleted_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'deleted_at' => 'datetime'
    ];

    const PERIODS = ['daily', 'weekly', 'monthly', 'yearly'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function getSpentAmount()
    {
        $start = $this->start_date;
        $end = $this->end_date ?: now();
        
        return Transaction::where('user_id', $this->user_id)
            ->where('type', 'expense')
            ->where('category_id', $this->category_id)
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');
    }

    public function getRemainingAmount()
    {
        return $this->amount - $this->getSpentAmount();
    }

    public function getPercentageUsed()
    {
        $spent = $this->getSpentAmount();
        if ($this->amount > 0) {
            return round(($spent / $this->amount) * 100, 2);
        }
        return 0;
    }

    public function isExceeded()
    {
        return $this->getSpentAmount() > $this->amount;
    }

    public function isWarning()
    {
        return $this->getPercentageUsed() >= 80;
    }
}