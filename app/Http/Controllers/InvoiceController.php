<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index() {}
    public function store(Request $request) {}
    public function show($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
    public function send($id) {}
    public function markPaid($id) {}
    public function downloadPdf($id) {}
}
