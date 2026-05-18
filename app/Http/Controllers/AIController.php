<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\AiConversation;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AIController extends Controller
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function getContext()
    {
        $userId = auth()->id();

        $monthlyIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $monthlyExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $profit = $monthlyIncome - $monthlyExpenses;
        $profitMargin = $monthlyIncome > 0 ? round(($profit / $monthlyIncome) * 100, 2) : 0;

        $topExpense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->select('category_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->orderBy('total', 'desc')
            ->first();

        return response()->json([
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'profit' => $profit,
            'profit_margin' => $profitMargin,
            'top_expense' => $topExpense?->category?->name ?? 'None',
            'business_type' => auth()->user()->role
        ]);
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array'
        ]);

        $userId = auth()->id();
        $message = $request->message;
        $history = $request->history ?? [];

        $response = $this->aiService->chat($userId, $message, $history);

        return response()->json([
            'reply' => $response,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function getAdvice(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500'
        ]);

        $context = $this->getContextData();
        $advice = $this->aiService->getFinancialAdvice(auth()->id(), $request->question, $context);

        return response()->json(['advice' => $advice]);
    }

    public function analyzeSpending()
    {
        $transactions = Transaction::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->with('category')
            ->get();

        $analysis = $this->aiService->analyzeSpending(auth()->id(), $transactions);

        return response()->json($analysis);
    }

    public function suggestBudgets()
    {
        $historicalSpending = Transaction::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [now()->subMonths(3), now()])
            ->select('category_id', \DB::raw('AVG(amount) as avg_spending'))
            ->groupBy('category_id')
            ->with('category')
            ->get()
            ->pluck('avg_spending', 'category.name')
            ->toArray();

        $suggestions = $this->aiService->suggestBudgets(auth()->id(), $historicalSpending);

        return response()->json($suggestions);
    }

    public function getInsights()
    {
        $userId = auth()->id();

        $revenue = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $expenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $profit = $revenue - $expenses;
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

        $topExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereYear('transaction_date', now()->year)
            ->select('category_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->orderBy('total', 'desc')
            ->limit(3)
            ->get()
            ->pluck('category.name')
            ->toArray();

        $data = [
            'revenue' => $revenue,
            'profit' => $profit,
            'margin' => $margin,
            'top_expenses' => $topExpenses,
            'inventory_turnover' => $this->calculateInventoryTurnover()
        ];

        $insights = $this->aiService->generateBusinessInsights($userId, $data);

        return response()->json($insights);
    }

    public function getConversations()
    {
        $conversations = AiConversation::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($conversations);
    }

    private function getContextData()
    {
        $userId = auth()->id();

        return [
            'monthly_income' => Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount'),
            'monthly_expenses' => Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount'),
            'profit_margin' => $this->getProfitMargin($userId),
            'top_expense' => $this->getTopExpenseCategory($userId),
            'business_type' => auth()->user()->role
        ];
    }

    private function getProfitMargin($userId)
    {
        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');
            
        $expenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');
            
        $profit = $income - $expenses;
        
        return $income > 0 ? round(($profit / $income) * 100, 2) : 0;
    }

    private function getTopExpenseCategory($userId)
    {
        $top = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->select('category_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->orderBy('total', 'desc')
            ->first();
            
        return $top?->category?->name ?? 'None';
    }

    private function calculateInventoryTurnover()
    {
        $cogs = Transaction::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->whereHas('category', function($q) {
                $q->where('name', 'Inventory');
            })
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');
            
        $avgInventory = \App\Models\Product::where('user_id', auth()->id())->avg('quantity');
        
        return $avgInventory > 0 ? round($cogs / $avgInventory, 2) : 0;
    }
}