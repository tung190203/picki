<?php

namespace App\Services;

use App\Enums\ClubNotificationType;
use App\Models\MiniTournament;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournamentStaff;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Services\Club\ClubFundContributionService;
use App\Enums\PaymentStatusEnum;
use App\Enums\ClubFundContributionStatus;
use App\Exceptions\BusinessException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MiniTournamentService
{
    public function __construct(
        protected ClubFundContributionService $fundContributionService,
    ) {
    }
    public function createTournament(array $data, int $userId): MiniTournament
    {
        $recurringSchedule = $data['recurring_schedule'] ?? null;
        $seriesId = $recurringSchedule ? Str::uuid()->toString() : null;

        // use_club_fund = true: kèo miễn phí cho member, CLB chi tiền.
        // has_fee và fee_amount vẫn giữ nguyên (số tiền CLB chi cho kèo đấu).

        $isClubFund = filter_var($data['use_club_fund'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isIncludedInClubFund = filter_var($data['included_in_club_fund'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasFee = filter_var($data['has_fee'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // fee_amount must be non-null: 0 when free, otherwise the sent amount
        $feeAmount = $hasFee ? (int) ($data['fee_amount'] ?? 0) : 0;

        // Exclude fee fields from spread — we'll set them explicitly
        $dataForCreate = collect($data)->except([
            'use_club_fund', 'included_in_club_fund', 'club_fund_collection_id', 'fee_amount',
        ])->toArray();

        $miniTournament = MiniTournament::create([
            ...$dataForCreate,
            'created_by' => $userId,
            'recurrence_series_id' => $seriesId,
            'use_club_fund' => $isClubFund,
            'included_in_club_fund' => $isIncludedInClubFund,
            'club_fund_collection_id' => $data['club_fund_collection_id'] ?? null,
            'fee_amount' => $feeAmount,
            'club_id' => $data['club_id'] ?? null,
        ]);

        // Creator always participates by default with confirmed payment status
        // (creator is exempt from payment or auto-confirmed)
        $participant = MiniParticipant::create([
            'mini_tournament_id' => $miniTournament->id,
            'user_id' => $userId,
            'is_confirmed' => true,
            'is_invited' => false,
            'payment_status' => PaymentStatusEnum::CONFIRMED,
        ]);

        // Gắn creator vào ClubFundCollection nếu kèo tính vào quỹ chung CLB
        $this->attachUserToMiniTournamentClubFund($miniTournament, $userId);

        // Tạo khoản thu cho chủ kèo nếu kèo có thu phí VÀ KHÔNG phải use_club_fund
        // - use_club_fund = true: CLB chi tiền, không thu phí từ member → KHÔNG tạo payment
        // - auto_split_fee = true: chỉ tạo payment khi kèo kết thúc (via command) → KHÔNG tạo payment ở đây
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee && !$miniTournament->use_club_fund) {
            $feePerPerson = $miniTournament->fee_amount;

            MiniParticipantPayment::create([
                'mini_tournament_id' => $miniTournament->id,
                'participant_id' => $participant->id,
                'user_id' => $userId,
                'amount' => $feePerPerson,
                'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                'paid_at' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => $userId,
            ]);
        }

        // Tạo batch occurrences nếu là recurring
        if ($miniTournament->isRecurring() && $seriesId) {
            $this->createBatchOccurrencesForNewSeries($miniTournament, $userId);
        }

        if ($miniTournament->club_id) {
            $club = \App\Models\Club\Club::find($miniTournament->club_id);
            if ($club) {
                app(\App\Services\Club\ClubNotificationService::class)->createNotification(
                    $club,
                    [
                        'club_notification_type_id' => ClubNotificationType::Event->value,
                        'title' => "Kèo mới: {$miniTournament->name}",
                        'content' => "CLB {$club->name} tổ chức kèo mới \"{$miniTournament->name}\". "
                            . "Thời gian: " . ($miniTournament->start_time ? $miniTournament->start_time->format('H:i d/m/Y') : 'N/A') . ". "
                            . ($miniTournament->address ? "Địa điểm: {$miniTournament->address}" : ''),
                        'priority' => \App\Enums\ClubNotificationPriority::Normal,
                        'status' => \App\Enums\ClubNotificationStatus::Sent,
                        'metadata' => [
                            'entity_type' => 'mini_tournament',
                            'entity_id' => $miniTournament->id,
                        ],
                    ],
                    $userId
                );
            }
        }

        return $miniTournament;
    }

    public function generateOccurrenceStartTimesForPeriod(MiniTournament $tournament): array
    {
        $schedule = $tournament->getRecurringScheduleRaw();
        if (!$schedule || empty($schedule['period'])) {
            return [];
        }

        $start = $tournament->start_time ? Carbon::parse($tournament->start_time) : Carbon::now();
        $timeString = $start->format('H:i:s');
        $period = $schedule['period'];
        $list = [];

        if ($period === 'weekly') {
            $weekDays = $schedule['week_days'] ?? [];
            if (empty($weekDays)) {
                return [];
            }
            $monthStart = $start->copy()->startOfMonth();
            $monthEnd = $start->copy()->endOfMonth();
            for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
                if (in_array((int) $d->dayOfWeek, array_map('intval', $weekDays), true)) {
                    $d->setTimeFromTimeString($timeString);
                    if ($d->gte($start)) {
                        $list[] = $d->copy();
                    }
                }
            }
            return $list;
        }

        $parts = $tournament->getRecurringDateParts();
        if (!$parts) {
            return [];
        }

        $day = (int) $parts['day'];
        $month = (int) $parts['month'];

        if ($period === 'monthly') {
            for ($i = 0; $i < 3; $i++) {
                $base = $start->copy()->addMonths($i)->startOfMonth();
                $effectiveDay = min($day, $base->daysInMonth);
                $occurrence = $base->copy()->day($effectiveDay)->setTimeFromTimeString($timeString);
                if ($occurrence->gte($start)) {
                    $list[] = $occurrence;
                }
            }
            return $list;
        }

        if ($period === 'quarterly') {
            $monthPositionInQuarter = (($month - 1) % 3) + 1;
            $targetMonths = [$monthPositionInQuarter, $monthPositionInQuarter + 3, $monthPositionInQuarter + 6, $monthPositionInQuarter + 9];
            $year = $start->year;
            foreach ($targetMonths as $m) {
                $base = Carbon::create($year, $m, 1);
                $effectiveDay = min($day, $base->daysInMonth);
                $occurrence = Carbon::create($year, $m, $effectiveDay)->setTimeFromTimeString($timeString);
                if ($occurrence->gte($start)) {
                    $list[] = $occurrence;
                }
            }
            return $list;
        }

        if ($period === 'yearly') {
            for ($y = 0; $y < 2; $y++) {
                $year = $start->year + $y;
                $base = Carbon::create($year, $month, 1);
                $effectiveDay = min($day, $base->daysInMonth);
                $occurrence = Carbon::create($year, $month, $effectiveDay)->setTimeFromTimeString($timeString);
                if ($occurrence->gte($start)) {
                    $list[] = $occurrence;
                }
            }
            return $list;
        }

        return [];
    }

    private function createBatchOccurrencesForNewSeries(MiniTournament $firstTournament, int $userId): void
    {
        $seriesId = $firstTournament->recurrence_series_id;
        if (!$seriesId) {
            return;
        }

        $startTimes = $this->generateOccurrenceStartTimesForPeriod($firstTournament);
        $firstStart = $firstTournament->start_time ? Carbon::parse($firstTournament->start_time)->copy()->startOfMinute() : null;

        foreach ($startTimes as $nextStartTime) {
            $nextStart = $nextStartTime->copy()->startOfMinute();
            if ($firstStart && $nextStart->eq($firstStart)) {
                continue;
            }
            // Safety net: never create occurrences in the past
            if ($nextStart->lt(Carbon::now())) {
                continue;
            }
            $this->createNextOccurrenceIfMissing($firstTournament, $nextStartTime, $userId, $seriesId);
        }
    }

    public function createNextOccurrenceIfMissing(
        MiniTournament $tournament,
        Carbon $nextStartTime,
        int $userId,
        ?string $recurrenceSeriesId = null
    ): ?MiniTournament {
        $seriesId = $recurrenceSeriesId ?? $tournament->recurrence_series_id;
        if (!$seriesId) {
            return null;
        }

        $nextStart = $nextStartTime->copy()->startOfMinute();
        $exists = MiniTournament::where('recurrence_series_id', $seriesId)
            ->whereBetween('start_time', [$nextStart->copy(), $nextStart->copy()->endOfMinute()])
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->createNextOccurrence($tournament, $nextStartTime, $userId, $seriesId);
    }

    private function createNextOccurrence(MiniTournament $tournament, Carbon $nextStartTime, int $userId, ?string $recurrenceSeriesId = null): MiniTournament
    {
        $duration = $tournament->duration ?? ($tournament->end_time ? $tournament->start_time->diffInMinutes($tournament->end_time) : null);
        $nextEndTime = $duration ? $nextStartTime->copy()->addMinutes($duration) : null;

        $seriesId = $recurrenceSeriesId ?? $tournament->recurrence_series_id;

        // Replicate tournament but exclude only status and recurrence_series_cancelled_at
        // This ensures poster and qr_code_url are copied to the new occurrence
        $newTournament = $tournament->replicate([
            'status',
            'recurrence_series_cancelled_at',
        ]);

        $newTournament->start_time = $nextStartTime;
        $newTournament->end_time = $nextEndTime;
        $newTournament->recurrence_series_id = $seriesId;
        $newTournament->recurrence_series_cancelled_at = null;
        $newTournament->save();

        $this->syncStaffAndCreatorForOccurrence($tournament, $newTournament, $userId);

        return $newTournament;
    }

    private function syncStaffAndCreatorForOccurrence(MiniTournament $source, MiniTournament $target, int $userId): void
    {
        $source->loadMissing(['miniTournamentStaffs', 'participants']);

        // Copy staff roles from source occurrence.
        foreach ($source->miniTournamentStaffs as $staff) {
            MiniTournamentStaff::firstOrCreate([
                'mini_tournament_id' => $target->id,
                'user_id' => $staff->user_id,
                'role' => $staff->role,
            ]);
        }

        // If source has no organizer staff, fallback attach creator as organizer.
        $hasOrganizer = $target->miniTournamentStaffs()
            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->exists();

        if (!$hasOrganizer && $userId > 0) {
            MiniTournamentStaff::firstOrCreate([
                'mini_tournament_id' => $target->id,
                'user_id' => $userId,
                'role' => MiniTournamentStaff::ROLE_ORGANIZER,
            ]);
        }

        // Creator should always be a confirmed participant in each occurrence.
        $creatorParticipant = MiniParticipant::firstOrCreate(
            [
                'mini_tournament_id' => $target->id,
                'user_id' => $userId,
            ],
            [
                'is_confirmed' => true,
                'is_invited' => false,
                'payment_status' => PaymentStatusEnum::CONFIRMED,
            ]
        );

        // Gắn creator vào ClubFundCollection nếu kèo tính vào quỹ chung CLB
        $this->attachUserToMiniTournamentClubFund($target, $userId);

        // Tạo khoản thu cho creator nếu kèo có thu phí VÀ KHÔNG phải use_club_fund
        // - use_club_fund = true: CLB chi tiền → KHÔNG tạo payment
        // - auto_split_fee = true: chỉ tạo payment khi kèo kết thúc (via command) → KHÔNG tạo payment ở đây
        if ($target->has_fee && !$target->auto_split_fee && !$target->use_club_fund) {
            MiniParticipantPayment::firstOrCreate(
                [
                    'mini_tournament_id' => $target->id,
                    'participant_id' => $creatorParticipant->id,
                ],
                [
                    'user_id' => $userId,
                    'amount' => $target->fee_amount ?? 0,
                    'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                    'paid_at' => now(),
                    'confirmed_at' => now(),
                    'confirmed_by' => $userId,
                ]
            );
        }
    }

    public function cancelRecurrenceSeries(string $seriesIdOrTournamentId, int $userId): int
    {
        $seriesId = Str::isUuid($seriesIdOrTournamentId)
            ? $seriesIdOrTournamentId
            : MiniTournament::where('id', $seriesIdOrTournamentId)->value('recurrence_series_id');

        if (!$seriesId) {
            throw new BusinessException('Chuỗi kèo đấu không tồn tại');
        }

        $tournamentIds = MiniTournament::where('recurrence_series_id', $seriesId)
            ->pluck('id')
            ->toArray();

        $hasPermission = MiniTournamentStaff::where('user_id', $userId)
            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->whereIn('mini_tournament_id', $tournamentIds)
            ->exists();

        if (!$hasPermission) {
            throw new BusinessException('Chỉ organizer mới có quyền hủy chuỗi kèo đấu');
        }

        $now = Carbon::now();

        $candidates = MiniTournament::with('staff')
            ->where('recurrence_series_id', $seriesId)
            ->where('status', '!=', MiniTournament::STATUS_CLOSED)
            ->where('start_time', '>', $now)
            ->get();

        $deleteIds = [];
        foreach ($candidates as $tournament) {
            if (!$tournament->allow_cancellation) {
                continue;
            }
            if ($tournament->isCancellationClosed($now)) {
                continue;
            }
            $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $tournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->exists();
            if ($hasCompletedMatch) {
                continue;
            }
            $deleteIds[] = $tournament->id;
        }

        DB::transaction(function () use ($deleteIds, $seriesId, $now) {
            if (!empty($deleteIds)) {
                MiniTournament::whereIn('id', $deleteIds)->delete();
            }

            // Đánh dấu chuỗi đã bị hủy (ngăn không cho tạo kèo mới)
            MiniTournament::where('recurrence_series_id', $seriesId)
                ->update(['recurrence_series_cancelled_at' => $now]);
        });

        // Invalidate club content cache
        if (!empty($deleteIds)) {
            $clubId = MiniTournament::whereIn('id', $deleteIds)->whereNotNull('club_id')->value('club_id');
            if ($clubId) {
                Cache::increment('club_content_version:' . $clubId);
            }
        }

        return count($deleteIds);
    }

    /**
     * Cập nhật cả chuỗi: cập nhật thông tin cho TẤT CẢ kèo trong chuỗi,
     * giữ nguyên participants, payments, matches đã có.
     */
    public function updateTournamentAsNewSeries(MiniTournament $tournament, array $data, int $userId): MiniTournament
    {
        $seriesId = $tournament->recurrence_series_id;
        if (!$seriesId) {
            throw new BusinessException('Kèo đấu này không thuộc chuỗi lặp lại');
        }

        $allTournaments = MiniTournament::where('recurrence_series_id', $seriesId)->get();

        if ($allTournaments->isEmpty()) {
            throw new BusinessException('Chuỗi kèo đấu không tồn tại');
        }

        // Lấy recurring_schedule mới (nếu có thay đổi)
        $newRecurringSchedule = $data['recurring_schedule'] ?? null;

        return DB::transaction(function () use ($allTournaments, $data, $newRecurringSchedule, $userId) {
            $updatedCount = 0;

            // Cập nhật thông tin cho TẤT CẢ kèo trong chuỗi
            foreach ($allTournaments as $t) {
                $updateData = [];

                // Chỉ cập nhật các trường được thay đổi trong data
                $fieldsToUpdate = [
                    'sport_id', 'name', 'description', 'play_mode', 'format',
                    'competition_location_id', 'is_private', 'has_fee', 'auto_split_fee',
                    'fee_amount', 'fee_description', 'payment_account_id', 'max_players',
                    'min_rating', 'max_rating', 'set_number', 'base_points',
                    'points_difference', 'max_points', 'gender', 'auto_approve',
                    'allow_participant_add_friends', 'allow_cancellation',
                    'cancellation_duration', 'apply_rule', 'poster',
                    'use_club_fund',
                ];

                foreach ($fieldsToUpdate as $field) {
                    if (array_key_exists($field, $data)) {
                        $updateData[$field] = $data[$field];
                    }
                }

                // Cập nhật duration nếu có thay đổi
                if (isset($data['duration'])) {
                    $updateData['duration'] = $data['duration'];
                    $startTime = $t->start_time;
                    if ($startTime) {
                        $startCarbon = $startTime instanceof Carbon ? $startTime : Carbon::parse($startTime);
                        $updateData['end_time'] = $startCarbon->copy()->addMinutes($data['duration']);
                    }
                }

                // Cập nhật recurring_schedule nếu có thay đổi
                if ($newRecurringSchedule !== null) {
                    $updateData['recurring_schedule'] = $newRecurringSchedule;
                }

                if (!empty($updateData)) {
                    $t->update($updateData);
                    $updatedCount++;
                }
            }

            return $allTournaments->first()->fresh();
        });
    }

    /**
     * Gắn user vào ClubFundCollection của mini-tournament nếu tournament thuộc quỹ chung CLB.
     * Chỉ thêm vào pivot assignedMembers (danh sách ai phải đóng).
     * KHÔNG tạo ClubFundContribution ở đây.
     * User nộp biên lai → tạo ClubFundContribution PENDING → Organizer confirm → wallet tx IN.
     */
    public function attachUserToMiniTournamentClubFund(MiniTournament $tournament, int $userId): void
    {
        if (!$tournament->club_fund_collection_id) {
            return;
        }

        $collection = $tournament->fundCollection;
        if (!$collection || !$collection->isActive()) {
            return;
        }

        $feeAmount = $tournament->fee_amount ?? 0;

        // Chỉ thêm vào pivot assignedMembers (danh sách ai phải đóng)
        // KHÔNG tạo ClubFundContribution ở đây
        // User nộp biên lai → tạo ClubFundContribution PENDING → Organizer confirm → wallet tx IN
        $collection->assignedMembers()->syncWithoutDetaching([
            $userId => ['amount_due' => $feeAmount],
        ]);
    }

    /**
     * Sync guest vào ClubFundContribution khi guest được thêm vào kèo CLB sau khi kèo đã tạo.
     *
     * Logic:
     * - Guest được organizer bảo lãnh → exempt (Confirmed + wallet tx)
     * - Guest được member bảo lãnh → PENDING (chờ nộp biên lai)
     * - Guest không ai bảo lãnh → PENDING
     */
    public function syncGuestToClubFund(
        MiniParticipant $participant,
        int $creatorId
    ): void {
        $tournament = $participant->miniTournament;

        if (!$participant->is_guest || !$tournament->club_fund_collection_id) {
            return;
        }

        $collection = $tournament->fundCollection;
        if (!$collection || !$collection->isActive()) {
            return;
        }

        // Kiểm tra đã có contribution chưa
        $existing = ClubFundContribution::where('club_fund_collection_id', $collection->id)
            ->where('user_id', $participant->user_id)
            ->first();
        if ($existing) {
            return;
        }

        $feeAmount = (float) ($tournament->fee_amount ?? 0);
        $isOrganizerGuarantor = $tournament->hasOrganizer($participant->guarantor_user_id);

        // Thêm vào assignedMembers trước
        $collection->assignedMembers()->syncWithoutDetaching([
            $participant->user_id => ['amount_due' => $isOrganizerGuarantor ? 0 : $feeAmount],
        ]);

        // Guest được organizer bảo lãnh → exempt (Confirmed + wallet tx)
        if ($isOrganizerGuarantor) {
            $this->fundContributionService->markOrganizerExempt(
                $collection,
                $participant->user_id,
                $creatorId,
                $feeAmount
            );
            return;
        }

        // Guest được member bảo lãnh hoặc không ai bảo lãnh → PENDING (chờ nộp biên lai)
        ClubFundContribution::create([
            'club_fund_collection_id' => $collection->id,
            'user_id' => $participant->user_id,
            'amount' => $feeAmount,
            'receipt_url' => null,
            'note' => 'Guest ' . ($participant->guest_name ?? '') . ' - chờ nộp biên lai',
            'status' => ClubFundContributionStatus::Pending,
        ]);
    }

    /**
     * Hủy toàn bộ chuỗi lặp lại của kèo thuộc CLB.
     * Chỉ admin/manager/secretary mới có quyền.
     */
    public function cancelRecurrenceSeriesForClub(\App\Models\Club\Club $club, string $tournamentId, int $userId): int
    {
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [\App\Enums\ClubMemberRole::Admin, \App\Enums\ClubMemberRole::Manager, \App\Enums\ClubMemberRole::Secretary], true)) {
            throw new BusinessException('Chỉ admin/manager/secretary mới có quyền hủy chuỗi kèo đấu');
        }

        $tournament = MiniTournament::find($tournamentId);
        if (!$tournament || (int) $tournament->club_id !== $club->id) {
            throw new BusinessException('Kèo đấu không tồn tại hoặc không thuộc CLB này');
        }

        $seriesId = $tournament->recurrence_series_id;
        if (!$seriesId) {
            throw new BusinessException('Kèo đấu không thuộc chuỗi lặp lại');
        }

        $now = Carbon::now();

        $candidates = MiniTournament::with('staff')
            ->where('recurrence_series_id', $seriesId)
            ->where('status', '!=', MiniTournament::STATUS_CLOSED)
            ->where('start_time', '>', $now)
            ->get();

        $deleteIds = [];
        foreach ($candidates as $t) {
            if (!$t->allow_cancellation) {
                continue;
            }
            if ($t->isCancellationClosed($now)) {
                continue;
            }
            $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $t->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->exists();
            if ($hasCompletedMatch) {
                continue;
            }
            $deleteIds[] = $t->id;
        }

        DB::transaction(function () use ($deleteIds, $seriesId, $now, $club) {
            if (!empty($deleteIds)) {
                MiniTournament::whereIn('id', $deleteIds)->delete();
            }

            MiniTournament::where('recurrence_series_id', $seriesId)
                ->update(['recurrence_series_cancelled_at' => $now]);
        });

        // Invalidate club content cache
        if (!empty($deleteIds)) {
            Cache::increment('club_content_version:' . $club->id);
        }

        return count($deleteIds);
    }
}
