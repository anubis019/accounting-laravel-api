<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BankController extends Controller
{
    public function getAccounts() {}
    public function createAccount(Request $request) {}
    public function updateAccount(Request $request, $id) {}
    public function deleteAccount($id) {}
    public function getTransactions($id) {}
    public function syncTransactions($id) {}
    public function importStatement($id) {}
    public function reconcile($id) {}
    public function getReconciliationStatus($id) {}
    public function matchTransaction(Request $request) {}
    public function unmatchTransaction($id) {}
}
