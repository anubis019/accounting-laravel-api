<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'code',
        'name',
        'type',
        'subtype',
        'parent_id',
        'is_system',
        'is_active',
        'opening_balance',
        'currency',
        'description'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function journalsAsDefaultDebit()
    {
        return $this->hasMany(Journal::class, 'default_debit_account_id');
    }

    public function journalsAsDefaultCredit()
    {
        return $this->hasMany(Journal::class, 'default_credit_account_id');
    }

    // Calculate current balance
    public function getCurrentBalanceAttribute()
    {
        $debit = $this->journalEntryLines()->sum('debit');
        $credit = $this->journalEntryLines()->sum('credit');

        // For asset and expense accounts: debit - credit
        // For liability, equity, income: credit - debit
        if (in_array($this->type, ['asset', 'expense'])) {
            return $debit - $credit + $this->opening_balance;
        } else {
            return $credit - $debit + $this->opening_balance;
        }
    }

    // Scope for active accounts
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope by type
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}