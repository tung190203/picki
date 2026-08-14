<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPushNotification\CampaignStatus;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPushNotification\CreateCampaignRequest;
use App\Http\Requests\Admin\AdminPushNotification\EstimateRecipientsRequest;
use App\Http\Requests\Admin\AdminPushNotification\ListCampaignsRequest;
use App\Http\Requests\Admin\AdminPushNotification\SendTestRequest;
use App\Http\Resources\Admin\AdminPushNotification\CampaignDetailResource;
use App\Http\Resources\Admin\AdminPushNotification\CampaignResource;
use App\Models\AdminPushNotificationCampaign;
use App\Services\Admin\AdminPushNotification\AdminPushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPushNotificationController extends Controller
{
    public function __construct(
        protected AdminPushNotificationService $service
    ) {}

    public function estimateRecipients(EstimateRecipientsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $count = $this->service->estimateRecipients(
            $validated['recipient_type'],
            $validated['recipient_config']
        );

        return ResponseHelper::success([
            'recipient_type' => $validated['recipient_type'],
            'estimated_recipient_count' => $count,
        ]);
    }

    public function preview(CreateCampaignRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $previewData = $this->service->preview(
            $validated['recipient_type'],
            $validated['recipient_config'],
            $validated['send_type'],
            $validated['scheduled_at'] ?? null
        );

        return ResponseHelper::success([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'action_type' => $validated['action_type'],
            'action_id' => $validated['action_id'] ?? null,
            'recipient_type' => $validated['recipient_type'],
            'recipient_config' => $validated['recipient_config'],
            ...$previewData,
        ]);
    }

    public function sendTest(SendTestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $admin = $request->user();

        try {
            $result = $this->service->sendTest(
                $admin,
                $validated['title'],
                $validated['content'],
                $request->file('image'),
                null,
                $validated['action_type'] ?? null,
                $validated['action_id'] ?? null
            );
        } catch (\App\Exceptions\BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        }

        return ResponseHelper::success($result, 'Đã gửi thông báo thử');
    }

    public function store(CreateCampaignRequest $request): JsonResponse
    {
        $admin = $request->user();
        $image = $request->file('image');

        $validated = $request->validated();
        $campaign = $this->service->createCampaign($validated, $image, $admin);

        return ResponseHelper::success(
            (new CampaignDetailResource($campaign->load('creator')))->toArray($request),
            'Đã tạo chiến dịch gửi thông báo',
            201
        );
    }

    public function index(ListCampaignsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 15);

        $query = AdminPushNotificationCampaign::query()
            ->with('creator:id,full_name,email')
            ->orderBy('created_at', 'desc');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['recipient_type'])) {
            $query->where('recipient_type', $validated['recipient_type']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        return ResponseHelper::paginated(
            CampaignResource::collection($paginated->getCollection())->toArray($request),
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $campaign = AdminPushNotificationCampaign::with('creator:id,full_name,email')->find($id);

        if (!$campaign) {
            return ResponseHelper::error('Chiến dịch không tồn tại', 404);
        }

        return ResponseHelper::success(
            (new CampaignDetailResource($campaign))->toArray($request)
        );
    }
}