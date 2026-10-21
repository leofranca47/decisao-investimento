<?php

namespace Tests\Feature;

use App\Models\AllocationTarget;
use App\Models\User;
use App\Services\AllocationTargetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AllocationTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_allocation_targets(): void
    {
        $user = User::factory()->create();

        $target = AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'acoes_br',
            'target_percentage' => 30.00,
            'min_percentage' => 20.00,
            'max_percentage' => 40.00,
            'tolerance_percentage' => 5.00,
        ]);

        $this->assertDatabaseHas('allocation_targets', [
            'id' => $target->id,
            'asset_class' => 'acoes_br',
        ]);

        $this->assertEquals('30.00', (string) $target->fresh()->target_percentage);
    }

    public function test_validates_total_allocation_percentage_equals_100(): void
    {
        $user = User::factory()->create();

        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'acoes_br',
            'target_percentage' => 40.00,
            'min_percentage' => 30.00,
            'max_percentage' => 50.00,
        ]);

        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'fiis',
            'target_percentage' => 40.00,
            'min_percentage' => 30.00,
            'max_percentage' => 50.00,
        ]);

        $service = new AllocationTargetService();

        // 80% total -> deve falhar
        $this->assertFalse($service->isValidTotalAllocation($user));

        // Adiciona 20% para fechar 100%
        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'renda_fixa',
            'target_percentage' => 20.00,
            'min_percentage' => 10.00,
            'max_percentage' => 30.00,
        ]);

        $this->assertTrue($service->isValidTotalAllocation($user));
        $this->assertEquals(100.0, $service->getTotalAllocationPercentage($user));
    }
}
