<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\BusinessHealthController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\TaxReportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\AccountingController;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/verify-email/{token}', [AuthController::class, 'verifyEmail']);

// M-PESA callbacks
Route::post('/mpesa/callback', [MpesaController::class, 'stkCallback']);
Route::post('/mpesa/c2b/confirmation', [MpesaController::class, 'c2bConfirmation']);
Route::post('/mpesa/c2b/validation', [MpesaController::class, 'c2bValidation']);
Route::post('/mpesa/b2c/result', [MpesaController::class, 'b2cResult']);
Route::post('/mpesa/b2c/timeout', [MpesaController::class, 'b2cTimeout']);

// WhatsApp webhook
Route::get('/whatsapp/webhook', [WhatsAppController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook']);

// Protected
Route::middleware(['auth:api'])->group(function () {
    // Auth profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/setup-2fa', [AuthController::class, 'setupTwoFactor']);
    Route::post('/enable-2fa', [AuthController::class, 'enableTwoFactor']);
    Route::post('/disable-2fa', [AuthController::class, 'disableTwoFactor']);

    // Dashboard & Reports
    Route::get('/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow']);
    Route::get('/reports/expense-breakdown', [ReportController::class, 'expenseBreakdown']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
    Route::post('/transactions/{id}/restore', [TransactionController::class, 'restore']);
    Route::post('/transactions/bulk-delete', [TransactionController::class, 'bulkDelete']);
    Route::get('/profit-loss', [TransactionController::class, 'profitLoss']);

    // Categories
    Route::get('/categories', [TransactionController::class, 'getCategories']);
    Route::post('/categories', [TransactionController::class, 'createCategory']);
    Route::put('/categories/{id}', [TransactionController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [TransactionController::class, 'deleteCategory']);

    // Accounting
    Route::get('/accounting/accounts', [AccountingController::class, 'getAccounts']);
    Route::post('/accounting/accounts', [AccountingController::class, 'createAccount']);
    Route::put('/accounting/accounts/{id}', [AccountingController::class, 'updateAccount']);
    Route::delete('/accounting/accounts/{id}', [AccountingController::class, 'deleteAccount']);
    Route::post('/accounting/setup-default-accounts', [AccountingController::class, 'setupDefaultAccounts']);

    Route::get('/accounting/journals', [AccountingController::class, 'getJournals']);
    Route::post('/accounting/journals', [AccountingController::class, 'createJournal']);

    Route::get('/accounting/entries', [AccountingController::class, 'getJournalEntries']);
    Route::post('/accounting/entries', [AccountingController::class, 'createJournalEntry']);
    Route::post('/accounting/entries/{id}/post', [AccountingController::class, 'postJournalEntry']);

    Route::get('/accounting/reports/general-ledger', [AccountingController::class, 'generalLedger']);
    Route::get('/accounting/reports/trial-balance', [AccountingController::class, 'trialBalance']);
    Route::get('/accounting/reports/balance-sheet', [AccountingController::class, 'balanceSheet']);

    // Products / Inventory
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/sell', [ProductController::class, 'sell']);
    Route::post('/products/{id}/restock', [ProductController::class, 'restock']);
    Route::get('/low-stock', [ProductController::class, 'lowStock']);

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::put('/tasks/{id}/complete', [TaskController::class, 'complete']);
    Route::get('/tasks/today', [TaskController::class, 'today']);
    Route::get('/tasks/upcoming', [TaskController::class, 'upcoming']);

    // Budgets
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::put('/budgets/{id}', [BudgetController::class, 'update']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);
    Route::get('/budgets/status', [BudgetController::class, 'status']);

    // Daily Plans
    Route::apiResource('daily-plans', DailyPlanController::class);
    Route::get('/daily-plans/today', [DailyPlanController::class, 'today']);
    Route::post('/daily-plans/{id}/complete', [DailyPlanController::class, 'complete']);

    // Savings Goals
    Route::apiResource('savings-goals', SavingsGoalController::class);
    Route::post('/savings-goals/{id}/contribute', [SavingsGoalController::class, 'addContribution']);
    Route::get('/savings-goals/dashboard', [SavingsGoalController::class, 'dashboard']);

    // Suppliers, Customers, Invoices
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{id}/send', [InvoiceController::class, 'send']);
    Route::post('/invoices/{id}/paid', [InvoiceController::class, 'markPaid']);
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf']);

    // Receipts
    Route::post('/receipts/upload', [ReceiptController::class, 'upload']);
    Route::get('/receipts', [ReceiptController::class, 'index']);
    Route::delete('/receipts/{id}', [ReceiptController::class, 'destroy']);
    Route::get('/receipts/{id}/download', [ReceiptController::class, 'download']);

    // Exports
    Route::get('/export/transactions', [ExportController::class, 'exportTransactions']);
    Route::get('/export/products', [ExportController::class, 'exportProducts']);
    Route::get('/export/profit-loss', [ExportController::class, 'exportProfitLoss']);
    Route::get('/export/budget-vs-actual', [ExportController::class, 'exportBudgetVsActual']);

    // AI
    Route::get('/ai/context', [AIController::class, 'getContext']);
    Route::post('/ai/chat', [AIController::class, 'chat']);
    Route::post('/ai/advice', [AIController::class, 'getAdvice']);
    Route::post('/ai/analyze', [AIController::class, 'analyzeSpending']);
    Route::post('/ai/budget-suggestions', [AIController::class, 'suggestBudgets']);
    Route::get('/ai/insights', [AIController::class, 'getInsights']);

    // M-PESA (authenticated)
    Route::post('/mpesa/stk-push', [MpesaController::class, 'initiatePayment']);
    Route::get('/mpesa/status/{id}', [MpesaController::class, 'checkStatus']);
    Route::post('/mpesa/b2c', [MpesaController::class, 'b2cPayment']);
    Route::get('/mpesa/balance', [MpesaController::class, 'accountBalance']);
    Route::get('/mpesa/transactions', [MpesaController::class, 'getTransactions']);

    // QR Codes
    Route::post('/qr/generate/payment', [QRCodeController::class, 'generatePaymentQR']);
    Route::post('/qr/generate/invoice/{invoiceId}', [QRCodeController::class, 'generateInvoiceQR']);
    Route::post('/qr/generate/product/{productId}', [QRCodeController::class, 'generateProductQR']);
    Route::post('/qr/generate/store', [QRCodeController::class, 'generateStoreQR']);
    Route::get('/qr/my-codes', [QRCodeController::class, 'getMyQRCodes']);
    Route::get('/qr/stats', [QRCodeController::class, 'getQRStats']);
    Route::delete('/qr/{id}', [QRCodeController::class, 'deactivateQR']);
    Route::get('/qr/{id}/download', [QRCodeController::class, 'downloadQR']);

    // Business Health
    Route::get('/health/score', [BusinessHealthController::class, 'getCurrentScore']);
    Route::get('/health/history', [BusinessHealthController::class, 'getHistoricalScores']);
    Route::get('/health/dashboard', [BusinessHealthController::class, 'getDashboard']);
    Route::get('/health/recommendations', [BusinessHealthController::class, 'getRecommendations']);
    Route::post('/health/refresh', [BusinessHealthController::class, 'refreshScore']);

    // Tax Reports
    Route::apiResource('tax-reports', TaxReportController::class);
    Route::post('/tax-reports/generate', [TaxReportController::class, 'generate']);
    Route::post('/tax-reports/{id}/submit', [TaxReportController::class, 'submit']);
    Route::get('/tax-reports/summary', [TaxReportController::class, 'summary']);

    // Bank
    Route::get('/bank/accounts', [BankController::class, 'getAccounts']);
    Route::post('/bank/accounts', [BankController::class, 'createAccount']);
    Route::put('/bank/accounts/{id}', [BankController::class, 'updateAccount']);
    Route::delete('/bank/accounts/{id}', [BankController::class, 'deleteAccount']);
    Route::get('/bank/accounts/{id}/transactions', [BankController::class, 'getTransactions']);
    Route::post('/bank/accounts/{id}/sync', [BankController::class, 'syncTransactions']);
    Route::post('/bank/accounts/{id}/import', [BankController::class, 'importStatement']);
    Route::post('/bank/accounts/{id}/reconcile', [BankController::class, 'reconcile']);
    Route::get('/bank/accounts/{id}/reconciliation-status', [BankController::class, 'getReconciliationStatus']);
    Route::post('/bank/match', [BankController::class, 'matchTransaction']);
    Route::delete('/bank/match/{id}', [BankController::class, 'unmatchTransaction']);

    // WhatsApp Admin
    Route::get('/whatsapp/conversations', [WhatsAppController::class, 'getConversations']);
    Route::get('/whatsapp/conversations/{id}/messages', [WhatsAppController::class, 'getMessages']);
    Route::post('/whatsapp/send', [WhatsAppController::class, 'sendFromDashboard']);

    // Audit Logs
    Route::get('/audit-logs', [AuditController::class, 'index']);
    Route::get('/audit-logs/summary', [AuditController::class, 'summary']);

    // Webhooks
    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
    Route::post('/webhooks/{id}/test', [WebhookController::class, 'test']);
});

Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Backend connected successfully'
    ]);
});