<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Chart of Accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('code')->unique(); // Account code like 1000, 2000
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->enum('subtype', [
                'current_asset', 'fixed_asset', 'current_liability', 'long_term_liability',
                'equity', 'revenue', 'cost_of_goods_sold', 'operating_expense', 'other_income', 'other_expense'
            ])->nullable();
            $table->uuid('parent_id')->nullable(); // For hierarchical accounts
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('accounts')->onDelete('set null');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'code']);
        });

        // Journals
        Schema::create('journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->string('code')->unique(); // Like SALE, PURCH, BANK, etc.
            $table->enum('type', ['sale', 'purchase', 'cash', 'bank', 'general', 'adjustment']);
            $table->uuid('default_debit_account_id')->nullable();
            $table->uuid('default_credit_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('default_debit_account_id')->references('id')->on('accounts');
            $table->foreign('default_credit_account_id')->references('id')->on('accounts');
            $table->index(['user_id', 'type']);
        });

        // Journal Entries
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('journal_id');
            $table->string('reference')->nullable(); // Invoice number, receipt, etc.
            $table->date('entry_date');
            $table->text('description');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->uuid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('transaction_id')->nullable(); // Link to original transaction
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('journal_id')->references('id')->on('journals');
            $table->foreign('posted_by')->references('id')->on('users');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'status']);
            $table->index('journal_id');
        });

        // Journal Entry Lines
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->uuid('partner_id')->nullable(); // Customer/Supplier
            $table->string('partner_type')->nullable(); // customer, supplier
            $table->timestamps();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->index('journal_entry_id');
            $table->index('account_id');
        });

        // Account Balances (for performance)
        Schema::create('account_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->date('balance_date');
            $table->decimal('debit_balance', 15, 2)->default(0);
            $table->decimal('credit_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0); // Calculated balance
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->unique(['account_id', 'balance_date']);
            $table->index('balance_date');
        });

        // Insert default accounts for each user (will be done in seeder)
        // But for now, let's add some system accounts
    }

    public function down()
    {
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }
};