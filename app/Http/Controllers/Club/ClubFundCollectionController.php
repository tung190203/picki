<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\StoreFundCollectionRequest;
use App\Http\Requests\Club\UpdateFundCollectionRequest;
use App\Http\Resources\Club\ClubFundCollectionResource;
use App\Http\Resources\Club\ClubFundContributionResource;
use App\Http\Resources\Club\ClubMyFundCollectionResource;
use App\Http\Resources\UserResource;
use App\Enums\ClubFundContributionStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;
use App\Services\Club\ClubFundCollectionService;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;

class ClubFundCollectionController extends Controller
{
    public function __construct(
        protected ClubFundCollectionService $collectionService
    ) {
    }

    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        $collections = $this->collectionService->getCollections($club, $validated);

        $data = ['collections' => ClubFundCollectionResource::collection($collections)];
        $meta = [
            'current_page' => $collections->currentPage(),
            'per_page' => $collections->perPage(),
            'total' => $collections->total(),
            'last_page' => $collections->lastPage(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách đợt thu thành công', 200, $meta);
    }

    public function store(StoreFundCollectionRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $data = $request->validated();

            $includedInClubFund = isset($data['included_in_club_fund']) ? (bool) $data['included_in_club_fund'] : true;

            // Luôn lưu QR nếu có upload, bất kể có bật thu vào quỹ chung hay không (để làm fallback)
            if ($request->hasFile('qr_image')) {
                $data['qr_code_url'] = app(ImageOptimizationService::class)->optimizeThumbnail(
                    $request->file('qr_image'),
                    'qr_codes',
                    90
                );
            } elseif (!empty($data['qr_code_id'])) {
                // Sử dụng QR từ collection khác
                $existingQr = ClubFundCollection::where('club_id', $clubId)
                    ->whereNotNull('qr_code_url')
                    ->where('qr_code_url', '!=', '')
                    ->find($data['qr_code_id']);
                if ($existingQr) {
                    $data['qr_code_url'] = $existingQr->qr_code_url;
                }
            }
            // Nếu không upload file mới và không dùng qr_code_id, giữ nguyên qr_code_url từ request (kế thừa từ activity)
            
            unset($data['qr_image'], $data['qr_code_id']);

            $collection = $this->collectionService->createCollection($club, $data, $userId);
            $this->loadCollectionForDetail($collection);
            $detail = $this->collectionService->getCollectionDetail($collection);

            return ResponseHelper::success(
                $this->formatCollectionDetailResponse($detail),
                'Tạo đợt thu thành công',
                201
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function show($clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$collection->club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        $this->loadCollectionForDetail($collection);
        $detail = $this->collectionService->getCollectionDetail($collection);

        return ResponseHelper::success(
            $this->formatCollectionDetailResponse($detail),
            'Lấy thông tin đợt thu thành công'
        );
    }

    public function update(UpdateFundCollectionRequest $request, $clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $data = $request->validated();
            if (!empty($data['end_date']) && $collection->start_date && $data['end_date'] < $collection->start_date->format('Y-m-d')) {
                return ResponseHelper::error('Ngày kết thúc phải sau ngày bắt đầu đợt thu', 422);
            }
            if (!empty($data['qr_code_id'])) {
                $existingQr = ClubFundCollection::where('club_id', $clubId)
                    ->whereNotNull('qr_code_url')
                    ->where('qr_code_url', '!=', '')
                    ->find($data['qr_code_id']);
                if ($existingQr) {
                    $data['qr_code_url'] = $existingQr->qr_code_url;
                }
                unset($data['qr_code_id']);
            }
            $collection = $this->collectionService->updateCollection($collection, $data, $userId);
            $collection->load(['creator', 'club', 'contributions.user']);

            return ResponseHelper::success(new ClubFundCollectionResource($collection), 'Cập nhật đợt thu thành công');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'active') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function destroy($clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->collectionService->cancelCollection($collection, $userId);
            return ResponseHelper::success('Đợt thu đã được hủy');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'active') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function remind($clubId, $collectionId, $userId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $requesterId = auth()->id();

        if (!$requesterId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$collection->club->isMember($requesterId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới sử dụng được chức năng này', 403);
        }

        try {
            $this->collectionService->sendReminder($collection, (int) $userId, $requesterId);
            return ResponseHelper::success(null, 'Đã gửi nhắc nhở thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function getQrCode($clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$collection->club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        $includedInClubFund = $collection->included_in_club_fund ?? true;

        if ($includedInClubFund) {
            // Ưu tiên lấy QR setting chung (mainWallet)
            $mainWallet = $collection->club->mainWallet;
            if ($mainWallet && !empty($mainWallet->qr_code_url)) {
                return ResponseHelper::success([
                    'qr_code_url' => $mainWallet->qr_code_url,
                    'qr_note'     => $mainWallet->qr_note,
                ], 'Lấy mã QR thành công');
            }

            // Fallback: nếu CLB chưa có QR chung, dùng QR riêng của collection (nếu có)
            if (!empty($collection->qr_code_url)) {
                return ResponseHelper::success([
                    'qr_code_url' => $collection->qr_code_url,
                    'qr_note'     => null,
                ], 'Lấy mã QR thành công');
            }

            return ResponseHelper::error('CLB chưa có mã QR thanh toán quỹ và đợt thu này cũng chưa có mã QR', 404);
        }

        // Không bật thu vào quỹ chung → chỉ lấy QR riêng của collection
        if (empty($collection->qr_code_url)) {
            return ResponseHelper::error('Đợt thu chưa có mã QR', 404);
        }

        return ResponseHelper::success([
            'qr_code_url' => $collection->qr_code_url,
            'qr_note'     => null,
        ], 'Lấy mã QR thành công');
    }

    public function destroyQrCode($clubId, $qrCodeId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->collectionService->deleteQrCode($club, (int) $qrCodeId, $userId);
            return ResponseHelper::success(null, 'Xóa mã QR thành công');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Mã QR không tồn tại', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function listQrCodes(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        $mainWallet = $club->mainWallet;

        if (!$mainWallet || empty($mainWallet->qr_code_url)) {
            return ResponseHelper::success([
                'qr_code_url' => null,
                'qr_note'     => null,
            ], 'CLB chưa có mã QR thanh toán quỹ');
        }

        return ResponseHelper::success([
            'qr_code_url' => $mainWallet->qr_code_url,
            'qr_note'     => $mainWallet->qr_note,
        ], 'Lấy mã QR thành công');
    }

    public function createQrCode(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $validated = $request->validate([
            'image'   => 'sometimes|image|mimes:png,jpg,jpeg,gif|max:5120',
            'qr_note' => 'nullable|string|max:1000',
        ]);

        try {
            $wallet = $this->collectionService->upsertMainWalletQr($club, $validated, $userId);

            return ResponseHelper::success([
                'qr_code_url' => $wallet->qr_code_url,
                'qr_note'     => $wallet->qr_note,
            ], 'Cập nhật mã QR thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function destroyMainQrCode($clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->collectionService->deleteMainWalletQr($club, $userId);
            return ResponseHelper::success(null, 'Xóa mã QR thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function getMyCollections(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        $validated = $request->validate([
            'payment_status' => 'sometimes|in:need_payment,pending,confirmed',
        ]);

        $result = $this->collectionService->getMyCollections($club, $userId);

        if (!empty($validated['payment_status'])) {
            $key = $validated['payment_status'];
            $result = [
                'need_payment' => $key === 'need_payment' ? $result['need_payment'] : collect(),
                'pending' => $key === 'pending' ? $result['pending'] : collect(),
                'confirmed' => $key === 'confirmed' ? $result['confirmed'] : collect(),
            ];
        }

        return ResponseHelper::success([
            'need_payment' => ClubMyFundCollectionResource::collection($result['need_payment']),
            'pending' => ClubMyFundCollectionResource::collection($result['pending']),
            'confirmed' => ClubMyFundCollectionResource::collection($result['confirmed']),
            'summary' => [
                'need_payment_count' => $result['need_payment']->count(),
                'pending_count' => $result['pending']->count(),
                'confirmed_count' => $result['confirmed']->count(),
            ],
        ], 'Lấy danh sách đợt thu của tôi thành công');
    }

    /**
     * Load relations and counts on collection so ClubFundCollectionResource outputs same shape everywhere.
     */
    private function loadCollectionForDetail(ClubFundCollection $collection): void
    {
        $collection->load([
            'creator',
            'club',
            'contributions.user',
            'assignedMembers',
        ]);
        $collection->loadCount([
            'contributions',
            'contributions as confirmed_count' => fn ($q) => $q->where('status', ClubFundContributionStatus::Confirmed),
            'contributions as pending_count' => fn ($q) => $q->where('status', ClubFundContributionStatus::Pending),
        ]);
    }

    /**
     * Format getCollectionDetail() result for API response (same shape for store and show).
     */
    private function formatCollectionDetailResponse(array $detail): array
    {
        return [
            'collection' => new ClubFundCollectionResource($detail['collection']),
            'approved_payments' => $detail['approved_payments']->map(fn ($item) => [
                'user' => new UserResource($item['user']),
                'amount_due' => $item['amount_due'],
                'amount_paid' => $item['amount_paid'],
                'payment_status' => $item['payment_status'],
                'paid_at' => $item['paid_at'],
                'contribution' => new ClubFundContributionResource($item['contribution']),
            ]),
            'waiting_approval_payments' => $detail['waiting_approval_payments']->map(fn ($item) => [
                'user' => new UserResource($item['user']),
                'amount_due' => $item['amount_due'],
                'amount_paid' => $item['amount_paid'],
                'payment_status' => $item['payment_status'],
                'paid_at' => $item['paid_at'],
                'contribution' => new ClubFundContributionResource($item['contribution']),
            ]),
            'no_payment_yet' => $detail['no_payment_yet']->map(fn ($item) => [
                'user' => new UserResource($item['user']),
                'amount_due' => $item['amount_due'],
                'amount_paid' => $item['amount_paid'],
                'payment_status' => $item['payment_status'],
                'paid_at' => $item['paid_at'],
                'contribution' => $item['contribution'] ? new ClubFundContributionResource($item['contribution']) : null,
            ]),
            'summary' => $detail['summary'],
        ];
    }
}
