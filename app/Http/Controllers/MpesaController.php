<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MpesaController extends Controller
{
    public function stkCallback(Request $request) {}
    public function c2bConfirmation(Request $request) {}
    public function c2bValidation(Request $request) {}
    public function b2cResult(Request $request) {}
    public function b2cTimeout(Request $request) {}
    public function initiatePayment(Request $request) {}
    public function checkStatus($id) {}
    public function b2cPayment(Request $request) {}
    public function accountBalance() {}
    public function getTransactions() {}
}
