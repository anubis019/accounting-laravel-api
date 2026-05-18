<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Str;

class AccountingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();

        foreach ($users as $user) {
            // Check if journals already exist for this user
            if (Journal::where('user_id', $user->id)->exists()) {
                continue;
            }

            $journals = [
                [
                    'name' => 'Sales Journal',
                    'code' => 'SALE',
                    'type' => 'sale',
                    'description' => 'Records sales transactions'
                ],
                [
                    'name' => 'Purchase Journal',
                    'code' => 'PURCH',
                    'type' => 'purchase',
                    'description' => 'Records purchase transactions'
                ],
                [
                    'name' => 'Cash Journal',
                    'code' => 'CASH',
                    'type' => 'cash',
                    'description' => 'Records cash transactions'
                ],
                [
                    'name' => 'Bank Journal',
                    'code' => 'BANK',
                    'type' => 'bank',
                    'description' => 'Records bank transactions'
                ],
                [
                    'name' => 'General Journal',
                    'code' => 'GEN',
                    'type' => 'general',
                    'description' => 'Records general accounting entries'
                ],
                [
                    'name' => 'Adjustment Journal',
                    'code' => 'ADJ',
                    'type' => 'adjustment',
                    'description' => 'Records adjusting entries'
                ]
            ];

            foreach ($journals as $journalData) {
                Journal::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'name' => $journalData['name'],
                    'code' => $journalData['code'],
                    'type' => $journalData['type'],
                    'description' => $journalData['description'],
                    'is_active' => true
                ]);
            }
        }
    }
}