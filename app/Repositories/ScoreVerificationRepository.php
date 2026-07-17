<?php

namespace App\Repositories;

use App\Enums\ScoreVerificationStatus;
use App\Models\ScoreVerificationRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ScoreVerificationRepository
{
    public function __construct(
        private ScoreVerificationRequest $model
    ) {}

    public function findOrFail(int $id): ScoreVerificationRequest
    {
        return $this->model->with(['user', 'reviewer'])->findOrFail($id);
    }

    public function findPendingByUser(int $userId): ?ScoreVerificationRequest
    {
        return $this->model
            ->pending()
            ->where('user_id', $userId)
            ->first();
    }

    public function getLatestByUser(int $userId): ?ScoreVerificationRequest
    {
        return $this->model
            ->with(['user'])
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    public function create(array $data): ScoreVerificationRequest
    {
        return $this->model->create($data);
    }

    public function updateStatus(
        ScoreVerificationRequest $request,
        ScoreVerificationStatus $status,
        ?int $reviewerId = null,
        ?string $rejectionReason = null
    ): ScoreVerificationRequest {
        return tap($request)->update([
            'status' => $status,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);
    }

    public function getWithLockForUpdate(int $id): ?ScoreVerificationRequest
    {
        return $this->model
            ->where('id', $id)
            ->where('status', ScoreVerificationStatus::PENDING)
            ->lockForUpdate()
            ->first();
    }

    public function isPending(int $id): bool
    {
        return $this->model->where('id', $id)->where('status', ScoreVerificationStatus::PENDING)->exists();
    }

    public function getSummary(): array
    {
        $result = $this->model
            ->selectRaw("
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        return [
            'pending' => (int) $result->pending,
            'approved' => (int) $result->approved,
            'rejected' => (int) $result->rejected,
        ];
    }

    public function getList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->with(['user']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', ScoreVerificationStatus::PENDING);
        }

        if (!empty($filters['score_type'])) {
            $query->where('score_type', $filters['score_type']);
        }
        if (!empty($filters['keyword'])) {
            $query->whereHas('user', fn($q) => $q->where('full_name', 'like', "%{$filters['keyword']}%"));
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
