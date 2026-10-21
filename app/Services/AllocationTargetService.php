<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AllocationTargetService
{
    /**
     * Retorna a soma atual das metas de alocação de um usuário.
     */
    public function getTotalAllocationPercentage(User $user): float
    {
        return (float) $user->allocationTargets()->sum('target_percentage');
    }

    /**
     * Verifica se a soma total das metas é exatamente 100%.
     */
    public function isValidTotalAllocation(User $user): bool
    {
        $total = $this->getTotalAllocationPercentage($user);
        return abs($total - 100.0) < 0.0001;
    }

    /**
     * Valida a soma total das metas, lançando ValidationException em caso de divergência.
     *
     * @throws ValidationException
     */
    public function validateTotalAllocation(User $user): void
    {
        $total = $this->getTotalAllocationPercentage($user);

        if (!$this->isValidTotalAllocation($user)) {
            throw ValidationException::withMessages([
                'allocation_targets' => "A soma das metas de alocação deve totalizar exatamente 100%. Soma atual: {$total}%.",
            ]);
        }
    }
}
