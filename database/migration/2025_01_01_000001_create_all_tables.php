<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Users
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['business', 'individual'])->default('individual');
            $table->string('business_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('daily_summary_enabled')->default(false);
            $table->boolean('budget_alerts_enabled')->default(true);
            $table->boolean('stock_alerts_enabled')->default(true);
            $table->boolean('transaction_alerts_enabled')->default(false);
            $table->json('whatsapp_preferences')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
            $table->index('email');
            $table->index('role');
            $table->index('phone');
        });

        // Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->string('color')->default('#3b82f6');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'type']);
        });

        // Transactions
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('category_id');
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->string('receipt_path')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->boolean('is_recurring')->default(false);
            $table->uuid('recurring_parent_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->string('mpesa_receipt')->nullable();
            $table->string('qr_code_id')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->foreign('invoice_id')->references('id')->on('invoices');
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type', 'transaction_date']);
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique()->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->string('image_path')->nullable();
            $table->string('barcode')->nullable();
            $table->integer('quantity_sold')->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->timestamp('last_sold_at')->nullable();
            $table->timestamp('last_alert_sent')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'quantity', 'min_stock_level']);
        });

        // Tasks
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('parent_task_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('category')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->boolean('recurring')->default(false);
            $table->enum('recurring_frequency', ['daily', 'weekly', 'monthly'])->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_task_id')->references('id')->on('tasks');
            $table->index(['user_id', 'status', 'due_date']);
        });

        // Budgets
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('category_id');
            $table->decimal('amount', 15, 2);
            $table->enum('period', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->index(['user_id', 'period']);
        });

        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_pin')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('name');
        });

        // Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_purchases', 15, 2)->default(0);
            $table->date('last_purchase_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('name');
        });

        // Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('customer_id');
            $table->string('invoice_number')->unique();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index('invoice_number');
            $table->index('status');
        });

        // Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('product_id')->nullable();
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });

        // Receipts
        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('transaction_id')->nullable();
            $table->string('filename');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->string('file_hash')->unique();
            $table->timestamp('uploaded_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->index('file_hash');
        });

        // Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('action');
            $table->string('table_name');
            $table->uuid('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['table_name', 'record_id']);
            $table->index('action');
            $table->index('created_at');
        });

        // AI Conversations
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('user_message');
            $table->text('ai_response');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('created_at');
        });

        // M-PESA Transactions
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable();
            $table->string('conversation_id')->nullable();
            $table->string('originator_conversation_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable()->unique();
            $table->decimal('amount', 15, 2);
            $table->string('phone_number');
            $table->string('account_reference')->nullable();
            $table->timestamp('transaction_date')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('type', ['stk_push', 'c2b', 'b2c', 'b2b']);
            $table->string('result_code')->nullable();
            $table->text('result_desc')->nullable();
            $table->text('remarks')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('checkout_request_id');
            $table->index('mpesa_receipt_number');
        });

        // WhatsApp
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('phone_number', 20);
            $table->string('wa_id', 100)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_subscribed')->default(true);
            $table->json('preferences')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('phone_number');
            $table->index('wa_id');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('direction', 10);
            $table->string('message_type', 50)->default('text');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('whatsapp_conversations')->onDelete('cascade');
            $table->index('direction');
            $table->index('status');
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->string('name');
            $table->string('template_id')->nullable();
            $table->text('content');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('name');
        });

        // QR Codes
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->uuid('reference_id');
            $table->string('code')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->string('payment_method')->nullable();
            $table->string('qr_image_url')->nullable();
            $table->integer('scans_count')->default(0);
            $table->integer('payments_count')->default(0);
            $table->decimal('collected_amount', 15, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('code');
            $table->index(['type', 'reference_id']);
        });

        Schema::create('qr_code_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('qr_code_id');
            $table->string('scanner_phone')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('location')->nullable();
            $table->boolean('converted_to_payment')->default(false);
            $table->timestamps();
            $table->foreign('qr_code_id')->references('id')->on('qr_codes')->onDelete('cascade');
            $table->index('qr_code_id');
        });

        // Health Scores
        Schema::create('health_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('score');
            $table->string('grade', 1);
            $table->json('components');
            $table->json('recommendations');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('created_at');
        });

        // Savings Goals
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->date('target_date')->nullable();
            $table->enum('status', ['active', 'achieved', 'failed'])->default('active');
            $table->decimal('monthly_contribution', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });

        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('savings_goal_id');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
            $table->foreign('savings_goal_id')->references('id')->on('savings_goals')->onDelete('cascade');
            $table->index('savings_goal_id');
        });

        // Daily Plans
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->date('plan_date');
            $table->json('activities')->nullable();
            $table->text('goals')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('rating')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'plan_date']);
        });

        // Tax Reports
        Schema::create('tax_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('period', ['monthly', 'quarterly', 'annually']);
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->integer('quarter')->nullable();
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('vat_charged', 15, 2)->default(0);
            $table->decimal('vat_paid', 15, 2)->default(0);
            $table->decimal('vat_due', 15, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'filed', 'amended'])->default('draft');
            $table->date('filing_date')->nullable();
            $table->string('kra_receipt')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'period', 'year', 'month', 'quarter']);
            $table->index(['user_id', 'status']);
        });

        // Bank Accounts
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('account_type');
            $table->string('currency', 3)->default('KES');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('account_number');
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_account_id');
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['debit', 'credit']);
            $table->string('reference')->nullable();
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->uuid('matched_transaction_id')->nullable();
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
            $table->foreign('matched_transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->index(['bank_account_id', 'transaction_date']);
            $table->index('reconciled');
        });

        // Insert default WhatsApp templates
        DB::table('whatsapp_templates')->insert([
            [
                'name' => 'receipt',
                'content' => "📄 *RECEIPT*\n\nDate: {{date}}\nAmount: KES {{amount}}\nItems: {{items_count}}\nReceipt #: {{receipt_no}}\n\nThank you for your business!",
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'daily_summary',
                'content' => "📊 *DAILY SUMMARY*\n\n📈 Income: KES {{income}}\n📉 Expenses: KES {{expense}}\n💰 Net: KES {{net}}\n💵 Balance: KES {{balance}}\n\nReply 'details' for breakdown.",
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'invoice_reminder',
                'content' => "⏰ *INVOICE REMINDER*\n\nInvoice #: {{invoice_no}}\nAmount: KES {{amount}}\nDue Date: {{due_date}}\nDays Overdue: {{days_overdue}}\n\nPlease make payment to avoid penalties.",
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'low_stock',
                'content' => "⚠️ *LOW STOCK ALERT*\n\nProduct: {{product}}\nCurrent Stock: {{current}}\nMinimum Level: {{min}}\n\nPlease reorder soon!",
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'welcome',
                'content' => "👋 *Welcome to AcctSys!*\n\nYou can now manage your finances via WhatsApp.\n\nCommands:\n💰 'balance' - Check your balance\n📋 'last' - Last 5 transactions\n➕ 'expense 500 lunch' - Add expense\n💵 'income 1000 sale' - Add income\n📊 'summary' - Today's summary\n❓ 'help' - All commands",
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('tax_reports');
        Schema::dropIfExists('daily_plans');
        Schema::dropIfExists('savings_transactions');
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('health_scores');
        Schema::dropIfExists('qr_code_scans');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('mpesa_transactions');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};