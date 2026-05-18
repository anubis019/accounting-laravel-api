<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $table = 'receipts';
    
    protected $fillable = [
        'id', 'user_id', 'transaction_id', 'filename', 'file_path', 
        'file_type', 'file_size', 'file_hash', 'uploaded_at', 
        'created_at', 'updated_at', 'deleted_at'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }

    public function getUrl()
    {
        return Storage::disk('s3')->url($this->file_path);
    }
}