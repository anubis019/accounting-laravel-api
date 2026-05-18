<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::where('user_id', auth()->id())
            ->with('subtasks');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->overdue) {
            $query->overdue();
        }

        $tasks = $query->orderBy('due_date')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->paginate($request->per_page ?? 20);

        $stats = [
            'total' => Task::where('user_id', auth()->id())->count(),
            'pending' => Task::where('user_id', auth()->id())->where('status', 'pending')->count(),
            'completed' => Task::where('user_id', auth()->id())->where('status', 'completed')->count(),
            'overdue' => Task::where('user_id', auth()->id())->overdue()->count(),
            'today' => Task::where('user_id', auth()->id())->today()->count()
        ];

        return response()->json([
            'tasks' => $tasks,
            'stats' => $stats
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'category' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'recurring' => 'boolean',
            'recurring_frequency' => 'required_if:recurring,true|in:daily,weekly,monthly'
        ]);

        $task = Task::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'due_date' => $request->due_date,
            'category' => $request->category,
            'assigned_to' => $request->assigned_to,
            'recurring' => $request->recurring ?? false,
            'recurring_frequency' => $request->recurring_frequency
        ]);

        AuditLog::log(auth()->id(), 'create', 'tasks', $task->id, null, $task->toArray());

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'due_date' => 'nullable|date',
            'category' => 'nullable|string'
        ]);

        $oldValues = $task->toArray();
        $task->update($request->all());

        if ($request->status === 'completed' && $oldValues['status'] !== 'completed') {
            $task->complete();
        }

        AuditLog::log(auth()->id(), 'update', 'tasks', $task->id, $oldValues, $task->toArray());

        return response()->json($task);
    }

    public function destroy($id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);
        $task->delete();

        AuditLog::log(auth()->id(), 'delete', 'tasks', $task->id, $task->toArray(), null);

        return response()->json(['message' => 'Task deleted']);
    }

    public function complete($id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);
        $task->complete();

        AuditLog::log(auth()->id(), 'complete', 'tasks', $task->id, null, ['completed_at' => now()]);

        return response()->json($task);
    }

    public function today()
    {
        $tasks = Task::where('user_id', auth()->id())
            ->today()
            ->where('status', '!=', 'completed')
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json($tasks);
    }

    public function upcoming()
    {
        $tasks = Task::where('user_id', auth()->id())
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->where('status', '!=', 'completed')
            ->orderBy('due_date')
            ->get();

        return response()->json($tasks);
    }
}