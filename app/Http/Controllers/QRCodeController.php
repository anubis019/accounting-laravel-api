<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QRCodeController extends Controller
{
    public function generatePaymentQR(Request $request) {}
    public function generateInvoiceQR($invoiceId) {}
    public function generateProductQR($productId) {}
    public function generateStoreQR(Request $request) {}
    public function getMyQRCodes() {}
    public function getQRStats() {}
    public function deactivateQR($id) {}
    public function downloadQR($id) {}
    // alternate shorter-named methods
    public function generate(Request $request) {}
    public function getStats() {}
    public function deactivate($id) {}
}
