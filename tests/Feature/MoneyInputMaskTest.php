<?php

namespace Tests\Feature;

use App\Filament\Components\MoneyInput;
use App\Filament\Resources\FinancialGoalResource\Pages\CreateFinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MoneyInputMaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_input_has_correct_mask_and_prefix(): void
    {
        $input = MoneyInput::make('test_value');

        $this->assertEquals('R$', $input->getPrefixLabel());
        $this->assertNotNull($input->getMask());
        $this->assertStringContainsString('$money', (string) $input->getMask()->toHtml());
        $this->assertStringContainsString('.', (string) $input->getMask()->toHtml());
        $this->assertStringContainsString(',', (string) $input->getMask()->toHtml());
    }

    public function test_money_input_formats_and_dehydrates_brazilian_currency(): void
    {
        $input = MoneyInput::make('amount');

        // Teste de formatação (banco -> tela)
        $formatted = $input->formatValue(1500.50);
        $this->assertEquals('1.500,50', $formatted);

        // Teste de desseleção / desidratação (tela -> banco)
        $dehydrated = $input->parseValue('1.500,50');
        $this->assertEquals(1500.50, $dehydrated);

        $dehydratedLarge = $input->parseValue('25.750.120,99');
        $this->assertEquals(25750120.99, $dehydratedLarge);

        $dehydratedNull = $input->parseValue(null);
        $this->assertNull($dehydratedNull);
    }

    public function test_livewire_can_save_record_using_brazilian_currency_string(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateFinancialGoal::class)
            ->fillForm([
                'name' => 'Comprar Casa',
                'target_amount' => '350.000,50',
                'current_amount' => '50.000,00',
                'priority' => 'high',
                'liquidity_need' => 'medium',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('financial_goals', [
            'user_id' => $user->id,
            'name' => 'Comprar Casa',
            'target_amount' => 350000.50,
            'current_amount' => 50000.00,
        ]);
    }
}
