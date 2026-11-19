<?php

namespace App\Http\Controllers;

use App\Enums\ClubStatus;
use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionLocationResource;
use App\Http\Resources\FacilityResource;
use App\Models\Club\Club;
use App\Models\CompetitionLocation;
use App\Models\CompetitionLocationYard;
use App\Models\Facility;
use App\Models\Follow;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompetitionLocationController extends Controller
{
    private const VALIDATION_RULE = 'nullable';
    /**
     * Lọc và lấy danh sách địa điểm thi đấu (trình lọc sân bóng)
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'nullable',
            'lng' => 'nullable',
            'radius' => 'nullable|numeric|min:1',
            'minLat' => self::VALIDATION_RULE,
            'maxLat' => self::VALIDATION_RULE,
            'minLng' => self::VALIDATION_RULE,
            'maxLng' => self::VALIDATION_RULE,
            'sport_id' => 'nullable|integer|exists:sports,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'number_of_yards' => 'nullable|array',
            'number_of_yards.*' => 'integer|min:1',
            'keyword' => 'nullable|string|max:255',
            'is_followed' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
            'yard_type' => 'nullable|array',
            'yard_type.*' => 'integer|in:' . implode(',', CompetitionLocationYard::YARD_TYPE),
            'facility_id' => 'nullable|array',
            'facility_id.*' => 'integer|exists:facilities,id',
            'is_map' => 'nullable|boolean',
        ]);

        $query = CompetitionLocation::withFullRelations()->active();

        if (!empty($validated['lat']) && !empty($validated['lng'])) {
            $query->orderByDistance($validated['lat'], $validated['lng']);
        }
        // 1. NearBy
        if (!empty($validated['lat']) && !empty($validated['lng']) && !empty($validated['radius'])) {
            $query->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
        }

        // 2. Filter
        $query->filter($validated);

        // 3. Bounding Box
        $hasFilter = collect([
            'sport_id',
            'location_id',
            'is_followed',
            'keyword',
            'lat',
            'lng',
            'radius',
            'number_of_yards',
            'yard_type',
            'facility_id'
        ])->some(fn($key) => $request->filled($key));

        if (
            !$hasFilter &&
            (!empty($validated['minLat']) || !empty($validated['maxLat']) ||
                !empty($validated['minLng']) || !empty($validated['maxLng']))
        ) {
            $query->inBounds(
                $validated['minLat'],
                $validated['maxLat'],
                $validated['minLng'],
                $validated['maxLng']
            );
        }

        // 4. Phân trang hoặc lấy toàn bộ (nếu is_map=true)
        if (!empty($validated['is_map']) && $validated['is_map']) {
            $locations = $query->get();
            $meta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $locations->count(),
                'total' => $locations->count(),
            ];
        } else {
            $locations = $query->paginate($validated['per_page'] ?? CompetitionLocation::PER_PAGE);
            $meta = [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ];
        }

        // 5. Các dữ liệu bổ sung
        $yardTypes = CompetitionLocationYard::select('yard_type')
            ->distinct()
            ->get()
            ->map(fn($yard) => [
                'id' => $yard->yard_type,
                'name' => $yard->yard_type_name,
            ]);

        $data = [
            'competition_locations' => CompetitionLocationResource::collection($locations),
            'yard_types' => $yardTypes,
            'facilities' => FacilityResource::collection(Facility::all()),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách địa điểm thi đấu thành công', 200, $meta);
    }

    /**
     * Lấy danh sách kèo đấu & giải đấu của 1 sân đấu (màn Chi tiết sân).
     * - Nếu truyền `date` (YYYY-MM-DD): lọc các kèo/giải diễn ra trong ngày đó.
     * - Nếu không truyền `date`: lấy tất cả kèo/giải sắp diễn ra & đang diễn ra.
     */
    public function events(Request $request, int $id)
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'type' => 'nullable|string|in:all,mini_tournament,tournament',
        ]);

        $location = CompetitionLocation::active()->findOrFail($id);
        $type = $validated['type'] ?? 'all';
        $dateStr = $validated['date'] ?? null;

        $dayStart = $dateStr ? Carbon::parse($dateStr)->startOfDay() : null;
        $dayEnd = $dateStr ? Carbon::parse($dateStr)->endOfDay() : null;

        $miniTournaments = collect();
        $tournaments = collect();

        if ($type === 'all' || $type === 'mini_tournament') {
            $miniQuery = MiniTournament::query()
                ->where('competition_location_id', $location->id)
                ->with(['creator:id,full_name,avatar_url', 'club:id,name'])
                ->withCount(['participants as participants_count' => fn($q) => $q->where('is_confirmed', true)]);

            if ($dayStart && $dayEnd) {
                CompetitionLocation::dateOverlapMiniConstraint($miniQuery, $dayStart, $dayEnd);
            } else {
                CompetitionLocation::activeMiniTournamentsConstraint($miniQuery);
            }

            $miniTournaments = $miniQuery->get([
                'id',
                'competition_location_id',
                'club_id',
                'name',
                'status',
                'session_status',
                'start_time',
                'end_time',
                'max_players',
                'has_fee',
                'fee_amount',
                'created_by',
            ])->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => 'mini_tournament',
                'status' => $t->status,
                'session_status' => $t->session_status,
                'start_time' => $t->start_time?->toISOString(),
                'end_time' => $t->end_time?->toISOString(),
                'start_date' => $t->start_time?->format('Y-m-d'),
                'max_players' => $t->max_players,
                'participants_count' => (int) ($t->participants_count ?? 0),
                'has_fee' => (bool) $t->has_fee,
                'fee_amount' => $t->fee_amount,
                'club' => $t->club ? [
                    'id' => $t->club->id,
                    'name' => $t->club->name,
                ] : null,
                'created_by' => $this->formatCreator($t->creator),
            ])->values();
        }

        if ($type === 'all' || $type === 'tournament') {
            $tourQuery = Tournament::query()
                ->where('competition_location_id', $location->id)
                ->with([
                    'creator:id,full_name,avatar_url',
                    'club:id,name',
                    'teams' => fn($q) => $q->select(['id', 'tournament_id'])->withCount(['members', 'guestMembers']),
                ])
                ->withCount(['participants as participants_count' => fn($q) => $q->where('is_confirmed', true)]);

            if ($dayStart && $dayEnd) {
                CompetitionLocation::dateOverlapTournamentConstraint($tourQuery, $dayStart, $dayEnd);
            } else {
                CompetitionLocation::activeTournamentsConstraint($tourQuery);
            }

            $tournaments = $tourQuery->get([
                'id',
                'competition_location_id',
                'club_id',
                'name',
                'status',
                'start_date',
                'end_date',
                'max_team',
                'player_per_team',
                'poster_url',
                'created_by',
            ])->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => 'tournament',
                'status' => $t->status,
                'start_date' => $t->start_date ? Carbon::parse($t->start_date)->format('Y-m-d') : null,
                'end_date' => $t->end_date ? Carbon::parse($t->end_date)->format('Y-m-d') : null,
                'poster_url' => $t->poster_url,
                'max_players' => ($t->max_team && $t->player_per_team) ? (int) ($t->max_team * $t->player_per_team) : null,
                'participants_count' => (int) ($t->participants_count ?? 0),
                'max_team' => $t->max_team,
                'participated_team' => $t->teams
                    ->filter(fn($team) => (($team->members_count ?? 0) + ($team->guest_members_count ?? 0)) >= 2)
                    ->count(),
                'club' => $t->club ? [
                    'id' => $t->club->id,
                    'name' => $t->club->name,
                ] : null,
                'created_by' => $this->formatCreator($t->creator),
            ])->values();
        }

        return ResponseHelper::success([
            'competition_location_id' => $location->id,
            'date' => $dateStr,
            'mini_tournaments_count' => $miniTournaments->count(),
            'tournaments_count' => $tournaments->count(),
            'mini_tournaments' => $miniTournaments,
            'tournaments' => $tournaments,
        ], 'Lấy danh sách kèo đấu và giải đấu tại sân thành công');
    }

    /**
     * Lấy toàn bộ danh sách các CLB đang chọn sân này làm sân nhà (không phân trang).
     */
    public function homeClubs(int $id)
    {
        $location = CompetitionLocation::active()->findOrFail($id);

        $clubs = Club::query()
            ->where('status', ClubStatus::Active)
            ->whereHas('homeCourts', fn($q) => $q->where('competition_locations.id', $location->id))
            ->with(['profile:id,club_id,cover_image_url', 'creator:id,full_name,avatar_url'])
            ->withCount(['activeMembers as members_count'])
            ->orderByDesc('is_verified')
            ->orderByDesc('members_count')
            ->get();

        $items = $clubs->map(fn(Club $club) => [
            'id' => $club->id,
            'name' => $club->name,
            'logo_url' => $club->logo_url,
            'cover_image_url' => $club->profile?->cover_image_url,
            'address' => $club->address,
            'is_verified' => (bool) $club->is_verified,
            'members_count' => (int) ($club->members_count ?? 0),
            'created_by' => $this->formatCreator($club->creator),
        ])->values();

        return ResponseHelper::success([
            'competition_location_id' => $location->id,
            'clubs_count' => $items->count(),
            'clubs' => $items,
        ], 'Lấy danh sách CLB sân nhà thành công');
    }

    /**
     * Lưu / bỏ lưu sân đấu (nút tim trên card / chi tiết sân).
     */
    public function toggleSave(int $id)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $location = CompetitionLocation::active()->findOrFail($id);

        $existing = Follow::where('user_id', $userId)
            ->where('followable_type', CompetitionLocation::class)
            ->where('followable_id', $location->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isSaved = false;
            $message = 'Đã bỏ lưu sân đấu';
        } else {
            Follow::create([
                'user_id' => $userId,
                'followable_type' => CompetitionLocation::class,
                'followable_id' => $location->id,
            ]);
            $isSaved = true;
            $message = 'Đã lưu sân đấu';
        }

        return ResponseHelper::success([
            'competition_location_id' => $location->id,
            'is_saved' => $isSaved,
        ], $message);
    }

    private function formatCreator($creator): ?array
    {
        if (!$creator) {
            return null;
        }

        return [
            'id' => $creator->id,
            'full_name' => $creator->full_name,
            'avatar_url' => $creator->avatar_url,
        ];
    }
}
