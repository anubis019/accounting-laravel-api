<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BusinessHealthController extends Controller
{
    public function getCurrentScore() {}
    public function getHistoricalScores() {}
    public function getDashboard() {}
    public function getRecommendations() {}
    public function refreshScore(Request $request) {}
}
