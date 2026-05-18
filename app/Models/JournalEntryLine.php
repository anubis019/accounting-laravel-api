<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
        'partner_id',
        'partner_type'
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2'
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

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function partner()
    {
        return $this->morphTo();
    }

    // Get the amount (debit or credit, whichever is non-zero)
    public function getAmountAttribute()
    {
        return $this->debit > 0 ? $this->debit : $this->credit;
    }

    // Get the type (debit or credit)
    public function getTypeAttribute()
    {
        return $this->debit > 0 ? 'debit' : 'credit';
    }
}