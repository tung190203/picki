<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMembershipStatus;
use App\Helpers\ResponseHelper;
use App\Http\Resources\ClubResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserTournamentResource;
use App\Http\Resources\UserMiniTournamentResource;
use App\Mail\VerifyNewEmailMail;
use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\Tournament;
use App\Models\User;
use App\Services\GeocodingService;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        $this->imageService = $imageService;
    }
    private const VALIDATION_RULE = 'nullable';
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
            'keyword' => 'nullable|string|max:255',
            'sport_id' => 'nullable|exists:sports,id',
            'per_page' => 'nullable|integer|min:1|max:200',
            'location_id' => 'nullable|exists:locations,id',
            'favourite_player' => 'nullable|boolean',
            'is_connected' => 'nullable|boolean',
            'gender' => 'nullable|in:' . implode(',', User::GENDER),
            'time_of_day' => 'nullable|array',
            'time_of_day.*' => 'in:' . implode(',', User::PLAY_TIME_OPTIONS),
            'rating' => 'nullable|array',
            'rating.*' => 'nullable',
            'online_recently' => 'nullable|boolean',
            'online_before_minutes' => 'nullable|integer|min:1',
            'recent_matches' => 'nullable|array',
            'recent_matches.*' => 'nullable|in:' . implode(',', User::RECENT_MATCHES_OPTIONS),
            'same_club_id' => 'nullable|array',
            'same_club_id.*' => 'exists:clubs,id',
            'verify_profile' => 'nullable|boolean',
            'achievement' => 'nullable',
            'is_map' => 'nullable|boolean',
        ]);

        $sport = Sport::where('slug', 'pickleball')->first();

        $query = User::query()
            ->with(['referee', 'follows', 'playTimes', 'sports', 'sports.sport', 'sports.scores', 'clubs'])
            ->where('id', '!=', auth()->id())
            ->filter($validated)
            ->visibleFor(auth()->user())
            ->withPickleballStats($sport?->id)
            ->withInteractionStatus(auth()->id());

        $hasFilter = collect([
            'sport_id',
            'keyword',
            'lat',
            'lng',
            'radius',
            'location_id',
            'favourite_player',
            'is_connected',
            'gender',
            'time_of_day',
            'rating',
            'online_recently',
            'online_before_minutes',
            'recent_matches',
            'same_club_id',
            'verify_profile',
            'achievement',
        ])->some(fn($key) => $request->filled($key));

        if (
            !$hasFilter &&
            (!empty($validated['minLat']) ||
                !empty($validated['maxLat']) ||
                !empty($validated['minLng']) ||
                !empty($validated['maxLng']))
        ) {
            $query->inBounds(
                $validated['minLat'],
                $validated['maxLat'],
                $validated['minLng'],
                $validated['maxLng']
            );
        }

        if (!empty($validated['lat']) && !empty($validated['lng'])) {
            $query->orderByDistance($validated['lat'], $validated['lng']);
        }

        if (!empty($validated['lat']) && !empty($validated['lng']) && !empty($validated['radius'])) {
            $query->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
        }

        if (!empty($validated['is_map']) && $validated['is_map']) {
            $users = $query->get();

            $data = [
                'users' => UserResource::collection($users),
                'clubs' => ClubResource::collection(auth()->user()->clubs),
            ];

            $meta = [
                'current_page' => 1,
                'per_page' => $users->count(),
                'total' => $users->count(),
                'last_page' => 1,
            ];
        } else {
            $paginated = $query->paginate($validated['per_page'] ?? User::PER_PAGE);

            $data = [
                'users' => UserResource::collection($paginated),
                'clubs' => ClubResource::collection(auth()->user()->clubs),
            ];

            $meta = [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ];
        }

        return ResponseHelper::success($data, 'Lấy danh sách người dùng thành công', 200, $meta);
    }
    public function show($id)
    {
        $user = User::withFullRelations()->find($id);
        if (!$user) {
            return ResponseHelper::error('Người dùng không tồn tại', 404);
        }
        return ResponseHelper::success(new UserResource($user), 'Lấy thông tin người dùng thành công');
    }

    public function getUserClubs(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::error('Người dùng không tồn tại', 404);
        }

        $validated = $request->validate([
            'role'   => 'nullable|array',
            'role.*' => [Rule::enum(ClubMemberRole::class)],
            'per_page' => 'nullable|integer|min:1|max:100',
            'page'   => 'nullable|integer|min:1',
        ]);

        $query = $user->clubs()
            ->where('club_members.membership_status', ClubMembershipStatus::Joined)
            ->where('club_members.status', \App\Enums\ClubMemberStatus::Active);

        if (!empty($validated['role'])) {
            $query->whereIn('club_members.role', $validated['role']);
        }

        $clubs = $query->withFullRelations()
            ->paginate($validated['per_page'] ?? 20);

        return ResponseHelper::success(
            ClubResource::collection($clubs),
            'Lấy danh sách câu lạc bộ của người dùng thành công',
            200,
            [
                'current_page' => $clubs->currentPage(),
                'last_page'    => $clubs->lastPage(),
                'per_page'     => $clubs->perPage(),
                'total'        => $clubs->total(),
            ]
        );
    }
    public function update(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:10|unique:users,phone',
            'avatar_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'location_id' => 'nullable|exists:locations,id',
            'about' => 'nullable|string|max:300',
            'password' => 'nullable|string|min:8',
            'is_profile_completed' => 'nullable|boolean',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|numeric|in:' . implode(',', User::GENDER),
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'sport_ids' => 'nullable|array',
            'sport_ids.*' => 'exists:sports,id',
            'score_value' => 'nullable|array',
            'score_value.*' => 'min:0',
            'visibility' => 'nullable|in:open,friend-only,private',
            'self_score' => 'nullable|string|max:255',
        ]);
        $user = User::findOrFail(auth()->id());
        $data = collect($validated)->except(['avatar_url', 'password', 'is_profile_completed', 'score_value', 'sport_ids'])->toArray();

        if (!empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }
        if ($request->hasFile('avatar_url')) {
            $this->imageService->deleteOldImage($user->avatar_url);
            $avatarPaths = $this->imageService->optimizeAvatar(
                $request->file('avatar_url'),
                'avatars'
            );

            $data['avatar_url'] = $avatarPaths['original'];
        }

        if ($request->hasFile('thumbnail')) {
            $this->imageService->deleteOldImage($user->thumbnail);
            $thumbnailPath = $this->imageService->optimizeThumbnail(
                $request->file('thumbnail'),
                'thumbnails',
                85
            );

            $data['thumbnail'] = $thumbnailPath;
        }

        if (!empty($validated['is_profile_completed']) && !$user->is_profile_completed) {
            $data['is_profile_completed'] = true;
        }

        $user->update($data);

        if (isset($validated['sport_ids'])) {
            $newSportIds = $validated['sport_ids'] ?? [];
            $newScoreValues = $validated['score_value'] ?? [];
            $oldSports = $user->sports()->get();
            foreach ($oldSports as $oldSport) {
                if (!in_array($oldSport->sport_id, $newSportIds)) {
                    $oldSport->scores()->delete();
                    $oldSport->delete();
                }
            }
            foreach ($newSportIds as $index => $sportId) {
                $scoreValue = $newScoreValues[$index] ?? null;
                $userSport = $user->sports()->where('sport_id', $sportId)->first();
                if (!$userSport) {
                    $userSport = $user->sports()->create([
                        'sport_id' => $sportId,
                        'tier' => null,
                    ]);
                }
                if (!empty($scoreValue)) {
                    $userSport->scores()
                        ->where('score_type', 'personal_score')
                        ->delete();
                    $userSport->scores()->create([
                        'score_type' => 'personal_score',
                        'score_value' => $scoreValue,
                    ]);
                    $hasDupr = $userSport->scores()
                        ->where('score_type', 'vndupr_score')
                        ->exists();

                    if (!$hasDupr) {
                        $userSport->scores()->create([
                            'score_type' => 'vndupr_score',
                            'score_value' => $scoreValue,
                        ]);
                    }
                }
            }
        }

        $data = [
            'user' => UserResource::make($user->fresh()->loadFullRelations()),
        ];

        return ResponseHelper::success($data, 'Cập nhật thông tin người dùng thành công');
    }

    public function searchLocation(Request $request, GeocodingService $geocoder)
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $results = $geocoder->search($validated['query']);

        return ResponseHelper::success($results, 'Tìm kiếm địa điểm thành công');
    }

    public function detailGooglePlace(Request $request, GeocodingService $geocoder)
    {
        $validated = $request->validate([
            'place_id' => 'required|string|max:255',
        ]);

        $result = $geocoder->getGooglePlaceDetail($validated['place_id']);

        return ResponseHelper::success($result, 'Lấy chi tiết địa điểm thành công');
    }

    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::error('Người dùng không tồn tại', 404);
        }
        if ($user->id !== auth()->id()) {
            return ResponseHelper::error('Bạn không có quyền xóa người dùng này', 403);
        }

        // Xóa ảnh đại diện khỏi storage
        if ($user->avatar_url) {
            $oldPath = str_replace(asset('storage/') . '/', '', $user->avatar_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        $user->update([
            'full_name' => 'Người dùng đã Xoá',
            'email' => 'delete_' . $user->email,
            'phone' => null,
            'thumbnail' => null,
            'gender' => null,
            'date_of_birth' => null,
            'latitude' => null,
            'longitude' => null,
            'address' => null,
            'email_verified_at' => null,
            'location_id' => null,
            'about' => null,
            'is_profile_completed' => 0,
        ]);

        $user->delete();

        return ResponseHelper::success(null, 'Xóa người dùng thành công');
    }
    public function changeEmail(Request $request)
    {
        $request->validate([
            'new_email' => 'required|email|unique:users,email',
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if ($user->id !== auth()->id()) {
            return ResponseHelper::error('Bạn không có quyền thay đổi email người dùng này', 403);
        }

        if (!Hash::check($request->password, $user->password)) {
            return ResponseHelper::error('Mật khẩu không đúng', 401, [
                'status_code' => 'INVALID_PASSWORD'
            ]);
        }

        $otp = rand(100000, 999999);
        DB::table('verification_codes')->updateOrInsert(
            ['type' => 'email_change', 'identifier' => $request->new_email],
            [
                'otp' => $otp,
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        try {
            Mail::to($request->new_email)->send(new VerifyNewEmailMail($otp));
        } catch (\Exception $e) {
            return ResponseHelper::error('Không thể gửi email xác minh', 500, [
                'status_code' => 'EMAIL_SEND_FAILED'
            ]);
        }

        return ResponseHelper::success([
            'status_code' => 'OTP_SENT'
        ], 'Mã OTP đã được gửi đến email mới');
    }

    public function verifyChangeEmail(Request $request)
    {
        $request->validate([
            'new_email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $user = $request->user();
        if ($user->id !== auth()->id()) {
            return ResponseHelper::error('Bạn không có quyền thay đổi email người dùng này', 403);
        }

        $record = DB::table('verification_codes')
            ->where('type', 'email_change')
            ->where('identifier', $request->new_email)
            ->where('user_id', $user->id)
            ->first();

        if (!$record) {
            return ResponseHelper::error('Không tìm thấy mã xác minh', 404, [
                'status_code' => 'OTP_NOT_FOUND'
            ]);
        }

        if ($record->otp !== $request->otp) {
            return ResponseHelper::error('Mã OTP không đúng', 400, [
                'status_code' => 'OTP_INVALID'
            ]);
        }

        if (now()->greaterThan($record->expires_at)) {
            return ResponseHelper::error('Mã OTP đã hết hạn', 400, [
                'status_code' => 'OTP_EXPIRED'
            ]);
        }

        if (User::where('email', $request->new_email)->where('id', '!=', $user->id)->exists()) {
            return ResponseHelper::error('Email đã được sử dụng', 400, [
                'status_code' => 'EMAIL_EXISTS'
            ]);
        }

        $user->email = $request->new_email;
        $user->save();

        DB::table('verification_codes')
            ->where('type', 'email_change')
            ->where('identifier', $request->new_email)
            ->delete();

        return ResponseHelper::success([
            'status_code' => 'COMPLETED',
            'user' => new UserResource($user->loadFullRelations())
        ], 'Đổi email thành công');
    }

    public function resendChangeEmailOtp(Request $request)
    {
        $request->validate(['new_email' => 'required|email']);

        $user = $request->user();
        if ($user->id !== auth()->id()) {
            return ResponseHelper::error('Bạn không có quyền thay đổi email người dùng này', 403);
        }

        $record = DB::table('verification_codes')
            ->where('type', 'email_change')
            ->where('identifier', $request->new_email)
            ->where('user_id', $user->id)
            ->first();

        if (!$record) {
            return ResponseHelper::error('Không tìm thấy yêu cầu đổi email', 404, [
                'status_code' => 'REQUEST_NOT_FOUND'
            ]);
        }

        $otp = rand(100000, 999999);
        DB::table('verification_codes')
            ->where('type', 'email_change')
            ->where('identifier', $request->new_email)
            ->where('user_id', $user->id)
            ->update([
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'updated_at' => now(),
            ]);

        try {
            Mail::to($request->new_email)->send(new VerifyNewEmailMail($otp));
        } catch (\Exception $e) {
            return ResponseHelper::error('Không thể gửi email xác minh', 500, [
                'status_code' => 'EMAIL_SEND_FAILED'
            ]);
        }

        return ResponseHelper::success([
            'status_code' => 'OTP_SENT'
        ], 'Mã OTP mới đã được gửi');
    }

    /**
     * Lấy lịch sử giải đấu của user
     * GET/POST /api/user/tournaments/list
     * params: user_id, sport_id, page, per_page
     * sort: ongoing tournaments first, then upcoming, then finished
     */
    public function tournamentsList(Request $request)
    {
        $validated = $request->validate([
            'user_id'   => 'required|exists:users,id',
            'sport_id'  => 'nullable|exists:sports,id',
            'per_page'  => 'nullable|integer|min:1|max:200',
            'page'      => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        $userId = $validated['user_id'];
        $sportId = 1; // Luôn luôn dùng sport_id = 1
        $perPage = $validated['per_page'] ?? Tournament::PER_PAGE;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;

        // Lấy user đang request (auth)
        $authUserId = auth()->id();

        // Kiểm tra có phải đang xem chính mình không
        $isOwnProfile = ($authUserId && $authUserId == $userId);

        // Tính overview với filter thống nhất: loại trừ ongoing tournament và private (nếu xem profile người khác)
        $overview = $this->getUserTournamentOverview($userId, $isOwnProfile);

            // Sort: open → upcoming → finished → canceled (ongoing tách riêng)
            $query = Tournament::query()
                ->with([
                    'createdBy', 'club', 'sport',
                    'tournamentStaffs', 'competitionLocation',
                    'teams', 'participants',
                    'tournamentTypes.groups.matches.homeTeam',
                    'tournamentTypes.groups.matches.awayTeam',
                    'tournamentTypes.groups.matches.results',
                ])
                ->where(function ($q) use ($userId) {
                    $q->whereHas('participants', fn($pq) => $pq->where('user_id', $userId))
                      ->orWhereHas('tournamentStaffs', fn($sq) => $sq->where('user_id', $userId)->whereIn('role', [1, 2]));
                })
            ->when($sportId, fn($q) => $q->where('sport_id', $sportId))
            ->when(!$isOwnProfile, function ($q) {
                $q->where('is_private', false);
            })
            ->when($dateFrom, fn($q) => $q->where('start_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('end_date', '<=', $dateTo))
            ->where('start_date', '<=', now())
            ->where('status', '!=', 1)
            // Ongoing: tách riêng, không hiển thị trong danh sách
            // Chỉ loại trừ khi end_date tồn tại VÀ chưa kết thúc; tournament không có end_date vẫn hiển thị trong list
            ->where(function ($q) {
                $q->whereRaw('NOT (status = 2 AND start_date <= NOW() AND end_date IS NOT NULL AND end_date >= NOW())');
            })
            ->select('tournaments.*')
            ->selectRaw("
                CASE
                    WHEN status = 2 THEN 0
                    WHEN status = 0 AND start_date > NOW() THEN 1
                    WHEN status = 3 THEN 2
                    WHEN status = 4 THEN 3
                    ELSE 4
                END AS sort_order,
                COALESCE(
                    CASE WHEN status IN (2, 0) THEN start_date END,
                    CASE WHEN status = 3 THEN end_date END,
                    CASE WHEN status = 4 THEN start_date END
                ) AS sort_date,
                CASE
                    WHEN status IN (2, 0) THEN 0
                    ELSE 1
                END AS date_sort_dir
            ")
            ->orderByRaw('sort_order ASC')
            ->orderByRaw('date_sort_dir ASC, sort_date DESC')
            ->orderBy('start_date', 'desc');

        // Tách 1 giải ongoing gần nhất
        $ongoingTournament = Tournament::query()
            ->with([
                'createdBy', 'club', 'sport',
                'tournamentStaffs', 'competitionLocation',
                'teams', 'participants',
                'tournamentTypes.groups.matches.homeTeam',
                'tournamentTypes.groups.matches.awayTeam',
                'tournamentTypes.groups.matches.results',
            ])
            ->where(function ($q) use ($userId) {
                $q->whereHas('participants', fn($pq) => $pq->where('user_id', $userId))
                  ->orWhereHas('tournamentStaffs', fn($sq) => $sq->where('user_id', $userId)->whereIn('role', [1, 2]));
            })
            ->when($sportId, fn($q) => $q->where('sport_id', $sportId))
            ->whereRaw('status = 2 AND start_date <= NOW() AND end_date IS NOT NULL AND end_date >= NOW()')
            ->orderBy('start_date', 'ASC')
            ->first();

        $tournaments = $query->paginate($perPage);

        $data = [
            'overview'            => $overview,
            'current_tournament' => $ongoingTournament ? new UserTournamentResource($ongoingTournament) : null,
            'tournaments'        => UserTournamentResource::collection($tournaments),
        ];

        $meta = [
            'current_page' => $tournaments->currentPage(),
            'last_page'    => $tournaments->lastPage(),
            'per_page'     => $tournaments->perPage(),
            'total'        => $tournaments->total(),
        ];

        return ResponseHelper::success($data, 'Lấy lịch sử giải đấu thành công', 200, $meta);
    }

    /**
     * Lấy lịch sử mini tournament của user
     * GET/POST /api/user/mini-tournaments/list
     * params: user_id, sport_id, page, per_page
     * sort: ongoing → upcoming → finished
     */
    public function miniTournamentsList(Request $request)
    {
        $validated = $request->validate([
            'user_id'  => 'required|exists:users,id',
            'sport_id' => 'nullable|exists:sports,id',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page'     => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        $userId = $validated['user_id'];
        $sportId = 1; // Luôn luôn dùng sport_id = 1
        $perPage = $validated['per_page'] ?? MiniTournament::PER_PAGE;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;

        $authUserId = auth()->id();
        $isOwnProfile = ($authUserId && $authUserId == $userId);

        // Tính overview với filter thống nhất: loại trừ ongoing tournament và private (nếu xem profile người khác)
        $overview = $this->getUserMiniTournamentOverview($userId, $isOwnProfile);

        // Sort: open → upcoming → finished → canceled (ongoing tách riêng)
        $query = MiniTournament::query()
            ->with([
                'sport',
                'club',
                'competitionLocation',
                'participants.user',
                'participants.team.members',
                'matches',
                'miniTournamentStaffs',
            ])
            ->where(function ($q) use ($userId) {
                $q->whereHas('participants', fn($pq) => $pq->where('user_id', $userId))
                  ->orWhereHas('miniTournamentStaffs', fn($sq) => $sq->where('user_id', $userId)->whereIn('role', [1]));
            })
            ->when($sportId, fn($q) => $q->where('sport_id', $sportId))
            ->when(!$isOwnProfile, function ($q) {
                $q->where('is_private', false);
            })
            ->when($dateFrom, fn($q) => $q->where('start_time', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('end_time', '<=', $dateTo))
            ->where('start_time', '<=', now())
            ->where('status', '!=', 1)
            // Ongoing: tách riêng, không hiển thị trong danh sách
            ->where(function ($q) {
                $q->whereRaw('NOT (status = 2 AND start_time <= NOW() AND end_time IS NOT NULL AND end_time >= NOW())');
            })
            ->select('mini_tournaments.*')
            ->selectRaw("
                CASE
                    WHEN status = 2 THEN 0
                    WHEN status = 0 AND start_time > NOW() THEN 1
                    WHEN status = 3 THEN 2
                    WHEN status = 4 THEN 3
                    ELSE 4
                END AS sort_order,
                COALESCE(
                    CASE WHEN status IN (2, 0) THEN start_time END,
                    CASE WHEN status = 3 THEN end_time END,
                    CASE WHEN status = 4 THEN start_time END
                ) AS sort_date,
                CASE
                    WHEN status IN (2, 0) THEN 0
                    ELSE 1
                END AS date_sort_dir
            ")
            ->orderByRaw('sort_order ASC')
            ->orderByRaw('date_sort_dir ASC, sort_date DESC')
            ->orderBy('start_time', 'desc');

        // Tách 1 giải ongoing gần nhất
        $ongoingMiniTournament = MiniTournament::query()
            ->with([
                'sport',
                'club',
                'competitionLocation',
                'participants.user',
                'participants.team.members',
                'matches',
                'miniTournamentStaffs',
            ])
            ->where(function ($q) use ($userId) {
                $q->whereHas('participants', fn($pq) => $pq->where('user_id', $userId))
                  ->orWhereHas('miniTournamentStaffs', fn($sq) => $sq->where('user_id', $userId)->whereIn('role', [1]));
            })
            ->when($sportId, fn($q) => $q->where('sport_id', $sportId))
            ->whereRaw('status = 2 AND start_time <= NOW() AND end_time IS NOT NULL AND end_time >= NOW()')
            ->orderBy('start_time', 'ASC')
            ->first();

        $miniTournaments = $query->paginate($perPage);

        $data = [
            'overview'              => $overview,
            'current_mini_tournament' => $ongoingMiniTournament ? new UserMiniTournamentResource($ongoingMiniTournament) : null,
            'mini_tournaments'     => UserMiniTournamentResource::collection($miniTournaments),
        ];

        $meta = [
            'current_page' => $miniTournaments->currentPage(),
            'last_page'    => $miniTournaments->lastPage(),
            'per_page'     => $miniTournaments->perPage(),
            'total'        => $miniTournaments->total(),
        ];

        return ResponseHelper::success($data, 'Lấy lịch sử mini tournament thành công', 200, $meta);
    }

    private function getUserTournamentOverview(int $userId, bool $isOwnProfile): array
    {
        // Filter align với main query: loại trừ ongoing tournament và private (nếu xem profile người khác)
        // Ongoing = status=2 AND start_date<=NOW() AND end_date IS NOT NULL AND end_date>=NOW()
        $ongoingCondition = 'NOT (tournaments.status = 2 AND tournaments.start_date <= NOW() AND tournaments.end_date IS NOT NULL AND tournaments.end_date >= NOW())';

        // Lấy tournament IDs user tham gia với vai trò VDV (sport_id = 1, đã bắt đầu, không ongoing, không private nếu không phải chính mình)
        $tournamentIdsAsParticipant = DB::table('participants')
            ->where('user_id', $userId)
            ->whereRaw("EXISTS (SELECT 1 FROM tournaments WHERE tournaments.id = participants.tournament_id AND tournaments.sport_id = 1 AND tournaments.status != 1 AND tournaments.start_date <= NOW() AND {$ongoingCondition}" . ($isOwnProfile ? '' : ' AND tournaments.is_private = false') . ")")
            ->pluck('tournament_id');

        // Lấy tournament IDs user tham gia với vai trò BTC/staff (sport_id = 1, đã bắt đầu, không ongoing, không private nếu không phải chính mình)
        $tournamentIdsAsStaff = DB::table('tournament_staff')
            ->where('user_id', $userId)
            ->whereIn('role', [1, 2])
            ->whereRaw("EXISTS (SELECT 1 FROM tournaments WHERE tournaments.id = tournament_staff.tournament_id AND tournaments.sport_id = 1 AND tournaments.status != 1 AND tournaments.start_date <= NOW() AND {$ongoingCondition}" . ($isOwnProfile ? '' : ' AND tournaments.is_private = false') . ")")
            ->pluck('tournament_id');

        // total_joined = distinct tournament (participant + staff/organizer)
        $allTournamentIds = $tournamentIdsAsParticipant
            ->merge($tournamentIdsAsStaff)
            ->unique();
        $totalJoined = $allTournamentIds->count();

        // total_created = đếm giải user tham gia với vai trò BTC
        $totalCreated = $tournamentIdsAsStaff->count();

        // Stats: chỉ tính khi user là VDV (có participant record)
        $totalWin = null;
        $totalMatches = null;
        $totalLose = null;

        if ($tournamentIdsAsParticipant->isNotEmpty()) {
            $userTeamIds = DB::table('team_members')
                ->where('user_id', $userId)
                ->pluck('team_id');

            if ($userTeamIds->isNotEmpty()) {
                $totalWin = DB::table('matches')
                    ->join('tournament_types', 'matches.tournament_type_id', '=', 'tournament_types.id')
                    ->join('tournaments', 'tournament_types.tournament_id', '=', 'tournaments.id')
                    ->whereIn('matches.winner_id', $userTeamIds)
                    ->where('matches.status', 'completed')
                    ->whereIn('tournaments.id', $tournamentIdsAsParticipant)
                    ->where('tournaments.sport_id', 1)
                    ->count();

                $totalMatches = DB::table('matches')
                    ->join('tournament_types', 'matches.tournament_type_id', '=', 'tournament_types.id')
                    ->join('tournaments', 'tournament_types.tournament_id', '=', 'tournaments.id')
                    ->where(function ($q) use ($userTeamIds) {
                        $q->whereIn('matches.home_team_id', $userTeamIds)
                          ->orWhereIn('matches.away_team_id', $userTeamIds);
                    })
                    ->where('matches.status', 'completed')
                    ->whereIn('tournaments.id', $tournamentIdsAsParticipant)
                    ->where('tournaments.sport_id', 1)
                    ->count();

                $totalLose = $totalMatches - $totalWin;
            }
        }

        return [
            'total_joined'   => $totalJoined,
            'total_created'  => $totalCreated,
            'total_matches'  => $totalMatches,
            'total_win'      => $totalWin,
            'total_lose'     => $totalLose,
        ];
    }

    private function getUserMiniTournamentOverview(int $userId, bool $isOwnProfile): array
    {
        // Filter align với main query: loại trừ ongoing tournament và private (nếu xem profile người khác)
        // Ongoing = status=2 AND start_time<=NOW() AND end_time IS NOT NULL AND end_time>=NOW()
        $ongoingCondition = 'NOT (mini_tournaments.status = 2 AND mini_tournaments.start_time <= NOW() AND mini_tournaments.end_time IS NOT NULL AND mini_tournaments.end_time >= NOW())';

        // Lấy mini tournament IDs user tham gia với vai trò VDV (sport_id = 1, đã bắt đầu, không ongoing, không private nếu không phải chính mình)
        $miniTournamentIdsAsParticipant = DB::table('mini_participants')
            ->where('user_id', $userId)
            ->whereRaw("EXISTS (SELECT 1 FROM mini_tournaments WHERE mini_tournaments.id = mini_participants.mini_tournament_id AND mini_tournaments.sport_id = 1 AND mini_tournaments.status != 1 AND mini_tournaments.start_time <= NOW() AND {$ongoingCondition}" . ($isOwnProfile ? '' : ' AND mini_tournaments.is_private = false') . ")")
            ->pluck('mini_tournament_id');

        // Lấy mini tournament IDs user tham gia với vai trò BTC (role = 1 = organizer, sport_id = 1, đã bắt đầu, không ongoing, không private nếu không phải chính mình)
        $miniTournamentIdsAsStaff = DB::table('mini_tournament_staff')
            ->where('user_id', $userId)
            ->whereIn('role', [1])
            ->whereRaw("EXISTS (SELECT 1 FROM mini_tournaments WHERE mini_tournaments.id = mini_tournament_staff.mini_tournament_id AND mini_tournaments.sport_id = 1 AND mini_tournaments.status != 1 AND mini_tournaments.start_time <= NOW() AND {$ongoingCondition}" . ($isOwnProfile ? '' : ' AND mini_tournaments.is_private = false') . ")")
            ->pluck('mini_tournament_id');

        // total_joined = distinct mini_tournament (participant + staff/organizer)
        $allMiniTournamentIds = $miniTournamentIdsAsParticipant
            ->merge($miniTournamentIdsAsStaff)
            ->unique();
        $totalJoined = $allMiniTournamentIds->count();

        // total_created = đếm giải user tham gia với vai trò BTC
        $totalCreated = $miniTournamentIdsAsStaff->count();

        // Stats: chỉ tính khi user là VDV (có participant record)
        $totalWin = null;
        $totalMatches = null;
        $totalLose = null;

        if ($miniTournamentIdsAsParticipant->isNotEmpty()) {
            $userMiniTeamIds = DB::table('mini_team_members')
                ->where('user_id', $userId)
                ->pluck('mini_team_id');

            if ($userMiniTeamIds->isNotEmpty()) {
                $totalWin = DB::table('mini_matches')
                    ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
                    ->whereIn('mini_matches.team_win_id', $userMiniTeamIds)
                    ->where('mini_matches.status', 'completed')
                    ->whereIn('mini_tournaments.id', $miniTournamentIdsAsParticipant)
                    ->where('mini_tournaments.sport_id', 1)
                    ->count();

                $totalMatches = DB::table('mini_matches')
                    ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
                    ->where(function ($q) use ($userMiniTeamIds) {
                        $q->whereIn('mini_matches.team1_id', $userMiniTeamIds)
                          ->orWhereIn('mini_matches.team2_id', $userMiniTeamIds);
                    })
                    ->where('mini_matches.status', 'completed')
                    ->whereIn('mini_tournaments.id', $miniTournamentIdsAsParticipant)
                    ->where('mini_tournaments.sport_id', 1)
                    ->count();

                $totalLose = $totalMatches - $totalWin;
            }
        }

        return [
            'total_joined'   => $totalJoined,
            'total_created'  => $totalCreated,
            'total_matches'  => $totalMatches,
            'total_win'      => $totalWin,
            'total_lose'     => $totalLose,
        ];
    }
}
