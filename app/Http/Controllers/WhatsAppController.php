<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function verify(Request $request) {}
    public function webhook(Request $request) {}
    public function getConversations() {}
    public function getMessages($id) {}
    public function sendFromDashboard(Request $request) {}
}
