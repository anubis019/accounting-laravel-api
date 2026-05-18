<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\AiConversation;
use Illuminate\Support\Facades\Log;

class AIService
{
    private $model = 'gpt-3.5-turbo';

    public function chat($userId, $message, $history = [])
    {
        $systemPrompt = "You are 'AcctSys AI', a financial assistant for Kenyan MSMEs and individuals.
                        You are helpful, practical, and concise. Provide actionable advice.
                        You can help with accounting, budgeting, saving, and business management.
                        Never give legal or investment advice that requires licensing.
                        Be friendly and speak simply. Always respond in English.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        $recentHistory = array_slice($history, -5);
        foreach ($recentHistory as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            $aiResponse = $response->choices[0]->message->content;

            AiConversation::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $userId,
                'user_message' => $message,
                'ai_response' => $aiResponse,
                'context' => json_encode(['history_count' => count($history)])
            ]);

            return $aiResponse;

        } catch (\Exception $e) {
            Log::error('AI Chat failed: ' . $e->getMessage());
            return "I'm having trouble connecting right now. Please check your internet connection and try again. If the problem persists, our support team is available via WhatsApp.";
        }
    }

    public function getFinancialAdvice($userId, $question, $context)
    {
        $prompt = "As a financial advisor for a Kenyan business, answer this question:\n\n";
        $prompt .= "Question: {$question}\n\n";
        $prompt .= "Business Context:\n";
        $prompt .= "- Monthly Income: KES " . number_format($context['monthly_income'] ?? 0) . "\n";
        $prompt .= "- Monthly Expenses: KES " . number_format($context['monthly_expenses'] ?? 0) . "\n";
        $prompt .= "- Profit Margin: {$context['profit_margin']}%\n";
        $prompt .= "- Top Expense: {$context['top_expense']}\n";
        $prompt .= "- Business Type: {$context['business_type']}\n\n";
        $prompt .= "Provide practical, actionable advice specific to Kenya. Be concise (3-4 sentences).";

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a practical financial advisor for Kenyan businesses.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 400
            ]);

            return $response->choices[0]->message->content;

        } catch (\Exception $e) {
            Log::error('AI Advice failed: ' . $e->getMessage());
            return $this->getFallbackAdvice($question, $context);
        }
    }

    public function analyzeSpending($userId, $transactions)
    {
        $spendingByCategory = [];
        foreach ($transactions as $transaction) {
            $catName = $transaction->category->name ?? 'Uncategorized';
            $spendingByCategory[$catName] = ($spendingByCategory[$catName] ?? 0) + $transaction->amount;
        }

        arsort($spendingByCategory);
        $topSpending = array_slice($spendingByCategory, 0, 5, true);

        $prompt = "Analyze this spending data for a Kenyan business:\n";
        foreach ($topSpending as $category => $amount) {
            $prompt .= "- {$category}: KES " . number_format($amount) . "\n";
        }
        $prompt .= "\nProvide 3 insights about spending patterns and 2 specific suggestions to reduce unnecessary expenses.";

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a cost optimization expert for Kenyan businesses.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 400
            ]);

            return [
                'analysis' => $response->choices[0]->message->content,
                'top_categories' => $topSpending
            ];

        } catch (\Exception $e) {
            return [
                'analysis' => 'Unable to analyze spending patterns at this time. Please review your transactions manually.',
                'top_categories' => $topSpending
            ];
        }
    }

    public function generateBusinessInsights($userId, $data)
    {
        $prompt = "Based on this business data for a Kenyan MSME, provide 3 key insights and 3 actionable recommendations:\n\n";
        $prompt .= "Revenue: KES " . number_format($data['revenue']) . "\n";
        $prompt .= "Profit: KES " . number_format($data['profit']) . "\n";
        $prompt .= "Profit Margin: {$data['margin']}%\n";
        $prompt .= "Top Expense Categories: " . implode(', ', $data['top_expenses']) . "\n";
        $prompt .= "Inventory Turnover: {$data['inventory_turnover']}\n\n";
        $prompt .= "Format the response as JSON with 'insights' (array of strings) and 'recommendations' (array of strings).";

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a business analyst for Kenyan MSMEs. Return valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.6,
                'max_tokens' => 600
            ]);

            $content = $response->choices[0]->message->content;
            preg_match('/\{.*\}/s', $content, $matches);

            if ($matches) {
                return json_decode($matches[0], true);
            }

            return $this->getFallbackInsights($data);

        } catch (\Exception $e) {
            return $this->getFallbackInsights($data);
        }
    }

    public function suggestBudgets($userId, $historicalSpending)
    {
        $prompt = "Based on these historical spending averages for a Kenyan business, suggest realistic monthly budgets:\n\n";
        foreach ($historicalSpending as $category => $amount) {
            $prompt .= "- {$category}: KES " . number_format($amount) . "\n";
        }
        $prompt .= "\nSuggest budgets that would help save 15% while being realistic. Return as JSON with category as key and budget amount as value.";

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a budgeting expert. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5,
                'max_tokens' => 400
            ]);

            $content = $response->choices[0]->message->content;
            preg_match('/\{.*\}/s', $content, $matches);

            if ($matches) {
                return json_decode($matches[0], true);
            }

            return $this->getFallbackBudgets($historicalSpending);

        } catch (\Exception $e) {
            return $this->getFallbackBudgets($historicalSpending);
        }
    }

    private function getFallbackAdvice($question, $context)
    {
        if (stripos($question, 'expense') !== false || stripos($question, 'save') !== false) {
            return "To reduce expenses, start by tracking every small purchase for a week. You might be surprised where your money goes. Then categorize expenses and identify the top 3 categories. Look for cheaper alternatives or negotiate with suppliers. Even a 10% reduction in top expenses can significantly boost your profit.";
        }

        if (stripos($question, 'profit') !== false) {
            return "To increase profit, focus on three areas: 1) Increase prices slightly - most customers won't notice 5-10%. 2) Reduce your top expense category by finding alternatives. 3) Improve inventory turnover by offering discounts on slow-moving stock. Small improvements in each area compound significantly.";
        }

        return "Thank you for your question. To give you the best advice, please provide more details about your specific situation. You can also check our reports section for detailed financial analysis of your business.";
    }

    private function getFallbackInsights($data)
    {
        $insights = [];
        $recommendations = [];

        if ($data['margin'] < 20) {
            $insights[] = "Your profit margin of {$data['margin']}% is below the recommended 20% for small businesses.";
            $recommendations[] = "Review your pricing strategy - consider a 10% price increase on your top 3 selling items.";
        } else {
            $insights[] = "Your profit margin of {$data['margin']}% is healthy. Well done!";
            $recommendations[] = "Consider investing your profits into marketing to grow your customer base.";
        }

        if (!empty($data['top_expenses'])) {
            $insights[] = "Your top expense category ({$data['top_expenses'][0]}) represents a significant portion of costs.";
            $recommendations[] = "Audit your {$data['top_expenses'][0]} expenses and look for cost-saving opportunities.";
        }

        $insights[] = "Regular financial review is key to business growth.";
        $recommendations[] = "Set a weekly 15-minute review of your financial dashboard to spot trends early.";

        return ['insights' => $insights, 'recommendations' => $recommendations];
    }

    private function getFallbackBudgets($historical)
    {
        $budgets = [];
        foreach ($historical as $category => $amount) {
            $budgets[$category] = round($amount * 0.85);
        }
        return $budgets;
    }
}