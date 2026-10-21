<?php

namespace Tests\Feature;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_financial_goal(): void
    {
        $user = User::factory()->create();

        $goal = FinancialGoal::create([
            'user_id' => $user->id,
            'name' => 'Aposentadoria Antecipada',
            'description' => 'Independência financeira aos 50 anos',
            'target_amount' => 1500000.50,
            'current_amount' => 150000.25,
            'target_date' => '2040-12-31',
            'priority' => 'high',
            'liquidity_need' => 'low',
            'notes' => 'Foco em FIIs e Ações com dividendos',
        ]);

        $this->assertDatabaseHas('financial_goals', [
            'id' => $goal->id,
            'name' => 'Aposentadoria Antecipada',
            'priority' => 'high',
        ]);

        $this->assertEquals('1500000.50', (string) $goal->fresh()->target_amount);
        $this->assertEquals('150000.25', (string) $goal->fresh()->current_amount);
        $this->assertTrue($goal->user->is($user));
    }
}
