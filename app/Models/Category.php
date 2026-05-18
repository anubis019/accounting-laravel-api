<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $table = 'categories';
    
    protected $fillable = [
        'id', 'user_id', 'name', 'type', 'color', 'description', 'is_system'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'category_id', 'id');
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'category_id', 'id');
    }
}