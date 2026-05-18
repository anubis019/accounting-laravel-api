<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::where('user_id', auth()->id())
            ->with('category');

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->start_date) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01|max:10000000',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
            'customer_name' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => auth()->id(),
                'category_id' => $request->category_id,
                'type' => $request->type,
                'amount' => $request->amount,
                'description' => $request->description,
                'transaction_date' => $request->transaction_date,
                'customer_name' => $request->customer_name,
                'supplier_id' => $request->supplier_id,
                'notes' => $request->notes
            ]);

            // Create corresponding journal entry
            $this->createJournalEntryFromTransaction($transaction);

            AuditLog::log(auth()->id(), 'create', 'transactions', $transaction->id, null, $transaction->toArray());

            DB::commit();

            return response()->json($transaction, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $transaction = Transaction::where('user_id', auth()->id())
            ->with(['category', 'supplier', 'receipts'])
            ->findOrFail($id);

        return response()->json($transaction);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'category_id' => 'exists:categories,id',
            'type' => 'in:income,expense',
            'amount' => 'numeric|min:0.01',
            'description' => 'nullable|string',
            'transaction_date' => 'date',
            'customer_name' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $oldValues = $transaction->toArray();
        $transaction->update($request->all());
        
        AuditLog::log(auth()->id(), 'update', 'transactions', $transaction->id, $oldValues, $transaction->toArray());

        return response()->json($transaction);
    }

    public function destroy($id)
    {
        $transaction = Transaction::where('user_id', auth()->id())->findOrFail($id);
        
        $transaction->delete();
        
        AuditLog::log(auth()->id(), 'delete', 'transactions', $transaction->id, $transaction->toArray(), null);

        return response()->json(['message' => 'Transaction deleted']);
    }

    public function restore($id)
    {
        $transaction = Transaction::withTrashed()
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        
        $transaction->restore();
        
        AuditLog::log(auth()->id(), 'restore', 'transactions', $transaction->id, null, $transaction->toArray());

        return response()->json(['message' => 'Transaction restored']);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id'
        ]);

        $deleted = Transaction::where('user_id', auth()->id())
            ->whereIn('id', $request->ids)
            ->delete();

        AuditLog::log(auth()->id(), 'bulk_delete', 'transactions', null, ['ids' => $request->ids], ['count' => $deleted]);

        return response()->json(['message' => "{$deleted} transactions deleted"]);
    }

    public function profitLoss(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth();
        $endDate = $request->end_date ?? now()->endOfMonth();

        $income = Transaction::where('user_id', auth()->id())
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $expenses = Transaction::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $profit = $income - $expenses;

        $incomeByCategory = Transaction::where('user_id', auth()->id())
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('category_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        $expenseByCategory = Transaction::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('category_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        return response()->json([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_income' => $income,
            'total_expenses' => $expenses,
            'profit' => $profit,
            'profit_margin' => $income > 0 ? round(($profit / $income) * 100, 2) : 0,
            'income_breakdown' => $incomeByCategory,
            'expense_breakdown' => $expenseByCategory
        ]);
    }

    public function getCategories()
    {
        $categories = Category::where('user_id', auth()->id())->get();
        return response()->json($categories);
    }

    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'color' => 'nullable|string'
        ]);

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'name' => $request->name,
            'type' => $request->type,
            'color' => $request->color ?? '#3b82f6'
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = Category::where('user_id', auth()->id())->findOrFail($id);
        
        $category->update($request->only(['name', 'color']));
        
        return response()->json($category);
    }

    public function deleteCategory($id)
    {
        $category = Category::where('user_id', auth()->id())->findOrFail($id);
        
        if ($category->is_system) {
            return response()->json(['error' => 'Cannot delete system category'], 400);
        }
        
        $category->delete();
        
        return response()->json(['message' => 'Category deleted']);
    }

    private function createJournalEntryFromTransaction($transaction)
    {
        // Find appropriate journal
        $journalType = $transaction->type === 'income' ? 'sale' : 'purchase';
        $journal = Journal::where('user_id', $transaction->user_id)
            ->where('type', $journalType)
            ->first();

        if (!$journal) {
            // Use general journal if specific one not found
            $journal = Journal::where('user_id', $transaction->user_id)
                ->where('type', 'general')
                ->first();
        }

        if (!$journal) {
            return; // No journal available
        }

        // Find cash/bank account (assume first cash account)
        $cashAccount = Account::where('user_id', $transaction->user_id)
            ->where('type', 'asset')
            ->whereIn('subtype', ['current_asset'])
            ->where('code', 'like', '1%')
            ->first();

        if (!$cashAccount) {
            return; // No cash account
        }

        // Find income/expense account based on category
        // For now, use a generic income/expense account
        $accountType = $transaction->type === 'income' ? 'income' : 'expense';
        $account = Account::where('user_id', $transaction->user_id)
            ->where('type', $accountType)
            ->first();

        if (!$account) {
            return; // No appropriate account
        }

        // Create journal entry
        $entry = JournalEntry::create([
            'id' => (string) Str::uuid(),
            'user_id' => $transaction->user_id,
            'journal_id' => $journal->id,
            'reference' => 'TXN-' . $transaction->id,
            'entry_date' => $transaction->transaction_date,
            'description' => $transaction->description ?? 'Transaction',
            'total_debit' => $transaction->amount,
            'total_credit' => $transaction->amount,
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
            'transaction_id' => $transaction->id
        ]);

        // Create journal entry lines
        if ($transaction->type === 'income') {
            // Debit cash, credit income
            JournalEntryLine::create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entry->id,
                'account_id' => $cashAccount->id,
                'debit' => $transaction->amount,
                'credit' => 0,
                'description' => $transaction->description
            ]);

            JournalEntryLine::create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'debit' => 0,
                'credit' => $transaction->amount,
                'description' => $transaction->description
            ]);
        } else {
            // Debit expense, credit cash
            JournalEntryLine::create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'debit' => $transaction->amount,
                'credit' => 0,
                'description' => $transaction->description
            ]);

            JournalEntryLine::create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entry->id,
                'account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => $transaction->amount,
                'description' => $transaction->description
            ]);
        }
    }