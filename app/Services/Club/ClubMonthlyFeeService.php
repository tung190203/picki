<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Exceptions\BusinessException;
use App\Models\Club\ClubMonthlyFee;
use Illuminate\Support\Collection;

class ClubMonthlyFeeService
{
    public function getFees(Club $club, array $filters): Collection
    {
        $query = $club->monthlyFees();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->get();
    }

    public function createFee(Club $club, array $data): ClubMonthlyFee
    {
        return ClubMonthlyFee::create([
            'club_id' => $club->id,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'VND',
            'due_day' => $data['due_day'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateFee(ClubMonthlyFee $fee, array $data): ClubMonthlyFee
    {
        $fee->update($data);
        return $fee;
    }

    public function deleteFee(ClubMonthlyFee $fee): void
    {
        if ($fee->payments()->exists()) {
            throw new BusinessException('Không thể xóa vì có payments');
        }

        $fee->delete();
    }
}
