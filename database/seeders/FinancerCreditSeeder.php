<?php

namespace Database\Seeders;

use App\Models\CreditBalance;
use App\Models\Financer;
use Illuminate\Database\Seeder;

class FinancerCreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $financers = Financer::all();

        foreach ($financers as $financer) {
            CreditBalance::updateOrCreate(
                [
                    'owner_type' => Financer::class,
                    'owner_id' => $financer->id,
                    'type' => 'ai_token',

                ],
                [
                    'balance' => config('ai.initial_token_amount'),
                ]
            );
        }
    }
}
