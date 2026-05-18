<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    
    protected $fillable = [
        'id', 'user_id', 'action', 'table_name', 'record_id', 
        'old_values', 'new_values', 'ip_address', 'user_agent', 
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public static function log($userId, $action, $table, $recordId, $old = null, $new = null)
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}