<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $table = 'ai_conversations';
    
    protected $fillable = [
        'id', 'user_id', 'user_message', 'ai_response', 'context', 
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'context' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}