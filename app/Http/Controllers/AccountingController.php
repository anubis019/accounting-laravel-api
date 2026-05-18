<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    // ===== ACCOUNTS =====

    public function getAccounts(Request $request)
    {
        $query = Account::where('user_id', auth()->id())
            ->with('parent');

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->active_only) {
            $query->active();
        }

        $accounts = $query->orderBy('code')->get();

        // Add current balance to each account
        $accounts->each(function ($account) {
            $account->current_balance = $account->current_balance;
        });

        return response()->json($accounts);
    }

    public function createAccount(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'subtype' => 'nullable|string',
            'parent_id' => 'nullable|exists:accounts,id',
            'opening_balance' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'description' => 'nullable|string'
        ]);

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'subtype' => $request->subtype,
            'parent_id' => $request->parent_id,
            'opening_balance' => $request->opening_balance ?? 0,
            'currency' => $request->currency ?? 'KES',
            'description' => $request->description
        ]);

        AuditLog::log(auth()->id(), 'create', 'accounts', $account->id, null, $account->toArray());

        return response()->json($account, 201);
    }

    public function updateAccount(Request $request, $id)
    {
        $account = Account::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'name' => 'string|max:255',
            'subtype' => 'nullable|string',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
            'description' => 'nullable|string'
        ]);

        $oldValues = $account->toArray();
        $account->update($request->only(['name', 'subtype', 'parent_id', 'is_active', 'description']));

        AuditLog::log(auth()->id(), 'update', 'accounts', $account->id, $oldValues, $account->toArray());

        return response()->json($account);
    }

    public function deleteAccount($id)
    {
        $account = Account::where('user_id', auth()->id())->findOrFail($id);

        if ($account->is_system) {
            return response()->json(['error' => 'Cannot delete system account'], 400);
        }

        // Check if account has entries
        if ($account->journalEntryLines()->exists()) {
            return response()->json(['error' => 'Cannot delete account with journal entries'], 400);
        }

        $account->delete();

        AuditLog::log(auth()->id(), 'delete', 'accounts', $account->id, $account->toArray(), null);

        return response()->json(['message' => 'Account deleted']);
    }

    // ===== JOURNALS =====

    public function getJournals(Request $request)
    {
        $query = Journal::where('user_id', auth()->id())
            ->with(['defaultDebitAccount', 'defaultCreditAccount']);

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->active_only) {
            $query->active();
        }

        $journals = $query->orderBy('code')->get();

        return response()->json($journals);
    }

    public function createJournal(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:journals,code',
            'type' => 'required|in:sale,purchase,cash,bank,general,adjustment',
            'default_debit_account_id' => 'nullable|exists:accounts,id',
            'default_credit_account_id' => 'nullable|exists:accounts,id',
            'description' => 'nullable|string'
        ]);

        $journal = Journal::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'default_debit_account_id' => $request->default_debit_account_id,
            'default_credit_account_id' => $request->default_credit_account_id,
            'description' => $request->description
        ]);

        AuditLog::log(auth()->id(), 'create', 'journals', $journal->id, null, $journal->toArray());

        return response()->json($journal, 201);
    }

    // ===== JOURNAL ENTRIES =====

    public function getJournalEntries(Request $request)
    {
        $query = JournalEntry::where('user_id', auth()->id())
            ->with(['journal', 'lines.account']);

        if ($request->journal_id) {
            $query->where('journal_id', $request->journal_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->start_date) {
            $query->where('entry_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('entry_date', '<=', $request->end_date);
        }

        $entries = $query->orderBy('entry_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($entries);
    }

    public function createJournalEntry(Request $request)
    {
        $request->validate([
            'journal_id' => 'required|exists:journals,id',
            'reference' => 'nullable|string|max:255',
            'entry_date' => 'required|date',
            'description' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'numeric|min:0',
            'lines.*.credit' => 'numeric|min:0',
            'lines.*.description' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            // Calculate totals
            $totalDebit = collect($request->lines)->sum('debit');
            $totalCredit = collect($request->lines)->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                return response()->json(['error' => 'Journal entry must be balanced'], 400);
            }

            $entry = JournalEntry::create([
                'id' => (string) Str::uuid(),
                'user_id' => auth()->id(),
                'journal_id' => $request->journal_id,
                'reference' => $request->reference,
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => 'draft'
            ]);

            // Create lines
            foreach ($request->lines as $lineData) {
                JournalEntryLine::create([
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineData['account_id'],
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                    'description' => $lineData['description'] ?? null
                ]);
            }

            AuditLog::log(auth()->id(), 'create', 'journal_entries', $entry->id, null, $entry->toArray());

            DB::commit();

            return response()->json($entry->load('lines.account'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create journal entry: ' . $e->getMessage()], 500);
        }
    }

    public function postJournalEntry($id)
    {
        $entry = JournalEntry::where('user_id', auth()->id())->findOrFail($id);

        if ($entry->status !== 'draft') {
            return response()->json(['error' => 'Only draft entries can be posted'], 400);
        }

        $entry->post();

        AuditLog::log(auth()->id(), 'post', 'journal_entries', $entry->id, ['status' => 'draft'], ['status' => 'posted']);

        return response()->json($entry);
    }

    // ===== FINANCIAL REPORTS =====

    public function generalLedger(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        $account = Account::where('user_id', auth()->id())->findOrFail($request->account_id);

        $entries = JournalEntry::where('user_id', auth()->id())
            ->where('status', 'posted')
            ->whereHas('lines', function ($query) use ($request) {
                $query->where('account_id', $request->account_id);
            })
            ->whereBetween('entry_date', [$request->start_date, $request->end_date])
            ->with(['lines' => function ($query) use ($request) {
                $query->where('account_id', $request->account_id);
            }])
            ->orderBy('entry_date')
            ->get();

        $openingBalance = $account->opening_balance;
        $balance = $openingBalance;

        $ledger = [
            'account' => $account,
            'period' => ['start' => $request->start_date, 'end' => $request->end_date],
            'opening_balance' => $openingBalance,
            'entries' => []
        ];

        foreach ($entries as $entry) {
            $line = $entry->lines->first();
            $debit = $line->debit;
            $credit = $line->credit;

            if (in_array($account->type, ['asset', 'expense'])) {
                $balance += $debit - $credit;
            } else {
                $balance += $credit - $debit;
            }

            $ledger['entries'][] = [
                'date' => $entry->entry_date,
                'reference' => $entry->reference,
                'description' => $entry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance
            ];
        }

        $ledger['closing_balance'] = $balance;

        return response()->json($ledger);
    }

    public function trialBalance(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfYear();
        $endDate = $request->end_date ?? now()->endOfYear();

        $accounts = Account::where('user_id', auth()->id())
            ->active()
            ->with(['journalEntryLines' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->posted()->whereBetween('entry_date', [$startDate, $endDate]);
                });
            }])
            ->get();

        $trialBalance = [];

        foreach ($accounts as $account) {
            $debit = $account->journalEntryLines->sum('debit');
            $credit = $account->journalEntryLines->sum('credit');

            if (abs($debit + $credit) > 0.01) { // Only include accounts with activity
                $trialBalance[] = [
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => in_array($account->type, ['asset', 'expense']) ? $debit - $credit : $credit - $debit
                ];
            }
        }

        return response()->json([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'accounts' => $trialBalance,
            'total_debit' => collect($trialBalance)->sum('debit'),
            'total_credit' => collect($trialBalance)->sum('credit')
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->date ?? now()->toDateString();

        $accounts = Account::where('user_id', auth()->id())
            ->active()
            ->with(['journalEntryLines' => function ($query) use ($asOfDate) {
                $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->posted()->where('entry_date', '<=', $asOfDate);
                });
            }])
            ->get();

        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($accounts as $account) {
            $debit = $account->journalEntryLines->sum('debit');
            $credit = $account->journalEntryLines->sum('credit');
            $balance = $account->opening_balance;

            if (in_array($account->type, ['asset', 'expense'])) {
                $balance += $debit - $credit;
            } else {
                $balance += $credit - $debit;
            }

            if (abs($balance) > 0.01) {
                $item = [
                    'account' => $account,
                    'balance' => $balance
                ];

                switch ($account->type) {
                    case 'asset':
                        $assets[] = $item;
                        break;
                    case 'liability':
                        $liabilities[] = $item;
                        break;
                    case 'equity':
                        $equity[] = $item;
                        break;
                }
            }
        }

        $totalAssets = collect($assets)->sum('balance');
        $totalLiabilities = collect($liabilities)->sum('balance');
        $totalEquity = collect($equity)->sum('balance');

        return response()->json([
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01
        ]);
    }

    // ===== SETUP DEFAULT ACCOUNTS =====

    public function setupDefaultAccounts()
    {
        $userId = auth()->id();

        // Check if accounts already exist
        if (Account::where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'Accounts already exist'], 400);
        }

        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1100', 'name' => 'Bank Account', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1400', 'name' => 'Fixed Assets', 'type' => 'asset', 'subtype' => 'fixed_asset'],

            // Liabilities
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2100', 'name' => 'Loans Payable', 'type' => 'liability', 'subtype' => 'long_term_liability'],

            // Equity
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'subtype' => 'equity'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'equity'],

            // Income
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income', 'subtype' => 'revenue'],
            ['code' => '4100', 'name' => 'Other Income', 'type' => 'income', 'subtype' => 'other_income'],

            // Expenses
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold'],
            ['code' => '5100', 'name' => 'Operating Expenses', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5200', 'name' => 'Other Expenses', 'type' => 'expense', 'subtype' => 'other_expense'],
        ];

        foreach ($accounts as $accountData) {
            Account::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'code' => $accountData['code'],
                'name' => $accountData['name'],
                'type' => $accountData['type'],
                'subtype' => $accountData['subtype'],
                'is_system' => true,
                'currency' => 'KES'
            ]);
        }

        return response()->json(['message' => 'Default accounts created']);
    }
}