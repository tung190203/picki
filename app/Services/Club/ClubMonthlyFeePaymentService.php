<?php

namespace App\Services\Club;

use App\Enums\ClubMonthlyFeePaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Club\Club;
use App\Models\Club\ClubMonthlyFeePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClubMonthlyFeePaymentService
{
    public function getPayments(Club $club, array $filters): LengthAwarePaginator
    {
        $query = ClubMonthlyFeePayment::where('club_id', $club->id)
            ->with(['user', 'monthlyFee', 'walletTransaction']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('period', 'desc')->paginate($perPage);
    }

    public function createPayment(Club $club, array $data, int $userId): ClubMonthlyFeePayment
    {
        $exists = ClubMonthlyFeePayment::where('club_id', $club->id)
            ->where('user_id', $userId)
            ->where('period', $data['period'])
            ->exists();

        if ($exists) {
            throw new BusinessException('Đã thanh toán phí cho tháng này');
        }

        return DB::transaction(function () use ($club, $data, $userId) {
            $payment = ClubMonthlyFeePayment::create([
                'club_id' => $club->id,
                'club_monthly_fee_id' => $data['club_monthly_fee_id'],
                'user_id' => $userId,
                'period' => $data['period'],
                'amount' => $data['amount'],
                'status' => 'pending',
            ]);

            $mainWallet = $club->mainWallet;
            if ($mainWallet) {
                $transaction = $mainWallet->transactions()->create([
                    'direction' => 'in',
                    'amount' => $data['amount'],
                    'source_type' => 'monthly_fee',
                    'source_id' => $payment->id,
                    'payment_method' => $data['payment_method'],
                    'status' => 'pending',
                    'reference_code' => $data['reference_code'] ?? null,
                    'description' => $data['description'] ?? null,
                    'created_by' => $userId,
                ]);

                $payment->update(['wallet_transaction_id' => $transaction->id]);
            }

            return $payment;
        });
    }

    public function getMemberPayments(Club $club, int $memberId, array $filters): LengthAwarePaginator
    {
        $query = ClubMonthlyFeePayment::where('club_id', $club->id)
            ->where('user_id', $memberId)
            ->with(['monthlyFee', 'walletTransaction']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('period', 'desc')->paginate($perPage);
    }

    public function getStatistics(Club $club, ?string $period = null): array
    {
        $query = ClubMonthlyFeePayment::where('club_id', $club->id);

        if ($period) {
            $query->where('period', $period);
        }

        return [
            'total_payments' => $query->count(),
            'paid_count' => (clone $query)->where('status', ClubMonthlyFeePaymentStatus::Paid)->count(),
            'pending_count' => (clone $query)->where('status', ClubMonthlyFeePaymentStatus::Pending)->count(),
            'failed_count' => (clone $query)->where('status', ClubMonthlyFeePaymentStatus::Failed)->count(),
            'total_amount' => (clone $query)->sum('amount'),
            'paid_amount' => (clone $query)->where('status', ClubMonthlyFeePaymentStatus::Paid)->sum('amount'),
        ];
    }
}
