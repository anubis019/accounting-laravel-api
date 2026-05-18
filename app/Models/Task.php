<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $table = 'tasks';
    
    protected $fillable = [
        'id', 'user_id', 'parent_task_id', 'title', 'description', 'status',
        'priority', 'due_date', 'completed_at', 'category', 'assigned_to',
        'recurring', 'recurring_frequency', 'created_at', 'updated_at', 'deleted_at'
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'recurring' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_task_id', 'id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id', 'id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->where('status', '!=', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('due_date', now()->toDateString());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function isOverdue()
    {
        return $this->due_date && $this->due_date < now() && $this->status !== 'completed';
    }

    public function complete()
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
        
        // Create recurring task if needed
        if ($this->recurring) {
            $this->createRecurringTask();
        }
        
        return $this;
    }

    private function createRecurringTask()
    {
        $nextDate = null;
        
        switch ($this->recurring_frequency) {
            case 'daily':
                $nextDate = now()->addDay();
                break;
            case 'weekly':
                $nextDate = now()->addWeek();
                break;
            case 'monthly':
                $nextDate = now()->addMonth();
                break;
        }
        
        if ($nextDate) {
            self::create([
                'user_id' => $this->user_id,
                'title' => $this->title,
                'description' => $this->description,
                'priority' => $this->priority,
                'due_date' => $nextDate,
                'category' => $this->category,
                'assigned_to' => $this->assigned_to,
                'recurring' => true,
                'recurring_frequency' => $this->recurring_frequency
            ]);
        }
    }
}