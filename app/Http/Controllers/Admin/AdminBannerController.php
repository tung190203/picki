<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminBannerResource;
use App\Models\Banner;
use App\Models\User;
use App\Services\ImageOptimizationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminBannerController extends Controller
{
    protected ImageOptimizationService $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Lấy danh sách banner (phân nhóm "Đang hiển thị" & "Đã kết thúc")
     * GET /api/admin/banners
     */
    public function index(Request $request)
    {
        $allBanners = Banner::with('creator')
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        $activeBanners = [];
        $endedBanners = [];

        foreach ($allBanners as $banner) {
            $resource = new AdminBannerResource($banner);
            $badge = $banner->status_badge;

            if (in_array($badge, ['live', 'expiring', 'scheduled'])) {
                $activeBanners[] = $resource;
            } else {
                $endedBanners[] = $resource;
            }
        }

        // Tái sử dụng 4 audience segments từ push notification
        $audienceSegments = [
            [
                'id' => 'ALL',
                'name' => 'Tất cả user',
                'estimated_count' => User::count(),
            ],
            [
                'id' => 'NEW_USERS',
                'name' => 'User mới (≤30 ngày)',
                'estimated_count' => User::where('created_at', '>=', Carbon::now()->subDays(30))->count(),
            ],
            [
                'id' => 'TOURNAMENT_USERS',
                'name' => 'User đã tham gia giải đấu',
                'estimated_count' => User::whereHas('participants')->count(),
            ],
            [
                'id' => 'INACTIVE_USERS',
                'name' => 'User không hoạt động (14+ ngày)',
                'estimated_count' => User::where('last_login', '<=', Carbon::now()->subDays(14))
                    ->orWhereNull('last_login')
                    ->count(),
            ],
        ];

        return ResponseHelper::success([
            'active_banners' => $activeBanners,
            'ended_banners' => $endedBanners,
            'audience_segments' => $audienceSegments,
            'total_active' => count($activeBanners),
            'total_ended' => count($endedBanners),
        ]);
    }

    /**
     * Tạo mới banner
     * POST /api/admin/banners
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'internal_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'link_type' => 'nullable|string|in:none,internal_deeplink,external_url',
            'link_value' => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'audience_segment_ids' => 'nullable|array',
            'display_order' => 'nullable|integer|min:1',
            'is_enabled' => 'nullable|boolean',
        ]);

        $imagePath = $validated['image_url'] ?? null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imagePath = $this->imageService->optimizeThumbnail($file, 'banners');
        }

        if (empty($imagePath)) {
            $imagePath = 'banners/default_banner.png';
        }

        $internalName = !empty($validated['internal_name']) 
            ? $validated['internal_name'] 
            : ('Banner #' . ((Banner::max('id') ?? 0) + 1));

        $nextOrder = $validated['display_order'] ?? ((Banner::max('display_order') ?? 0) + 1);

        $startDate = $validated['start_date'] ?? Carbon::today()->toDateString();
        $endDate = $validated['end_date'] ?? Carbon::today()->addDays(30)->toDateString();
        $segments = !empty($validated['audience_segment_ids']) ? $validated['audience_segment_ids'] : ['ALL'];

        $banner = Banner::create([
            'internal_name' => $internalName,
            'title' => $validated['title'] ?? $internalName,
            'subtitle' => $validated['subtitle'] ?? null,
            'image_url' => $imagePath,
            'link_type' => $validated['link_type'] ?? 'none',
            'link_value' => $validated['link_value'] ?? null,
            'link' => $validated['link_value'] ?? null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'audience_segment_ids' => $segments,
            'display_order' => $nextOrder,
            'order' => $nextOrder,
            'is_enabled' => filter_var($validated['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_active' => filter_var($validated['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'created_by' => Auth::id(),
        ]);

        return ResponseHelper::success(
            new AdminBannerResource($banner),
            'Tạo banner mới thành công',
            201
        );
    }

    /**
     * Chi tiết banner
     * GET /api/admin/banners/{banner}
     */
    public function show(Banner $banner)
    {
        $banner->load('creator');
        return ResponseHelper::success(new AdminBannerResource($banner));
    }

    /**
     * Cập nhật banner
     * POST /api/admin/banners/{banner} hoặc PUT /api/admin/banners/{banner}
     */
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'internal_name' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'link_type' => 'sometimes|required|string|in:none,internal_deeplink,external_url',
            'link_value' => 'nullable|string|max:500',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'audience_segment_ids' => 'sometimes|required|array|min:1',
            'display_order' => 'nullable|integer|min:1',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $banner->image_url = $this->imageService->optimizeThumbnail($file, 'banners');
        } elseif (!empty($validated['image_url'])) {
            $banner->image_url = $validated['image_url'];
        }

        if (isset($validated['internal_name'])) $banner->internal_name = $validated['internal_name'];
        if (array_key_exists('title', $validated)) $banner->title = $validated['title'];
        if (array_key_exists('subtitle', $validated)) $banner->subtitle = $validated['subtitle'];
        if (isset($validated['link_type'])) $banner->link_type = $validated['link_type'];
        if (array_key_exists('link_value', $validated)) {
            $banner->link_value = $validated['link_value'];
            $banner->link = $validated['link_value'];
        }
        if (isset($validated['start_date'])) $banner->start_date = $validated['start_date'];
        if (isset($validated['end_date'])) $banner->end_date = $validated['end_date'];
        if (isset($validated['audience_segment_ids'])) $banner->audience_segment_ids = $validated['audience_segment_ids'];
        if (isset($validated['display_order'])) {
            $banner->display_order = $validated['display_order'];
            $banner->order = $validated['display_order'];
        }
        if (isset($validated['is_enabled'])) {
            $isEnabled = filter_var($validated['is_enabled'], FILTER_VALIDATE_BOOLEAN);
            $banner->is_enabled = $isEnabled;
            $banner->is_active = $isEnabled;
        }

        $banner->save();

        return ResponseHelper::success(
            new AdminBannerResource($banner),
            'Cập nhật banner thành công'
        );
    }

    /**
     * Đổi thứ tự hiển thị banner qua kéo thả
     * POST /api/admin/banners/reorder
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:banners,id',
            'orders.*.display_order' => 'required|integer|min:1',
        ]);

        foreach ($validated['orders'] as $item) {
            Banner::where('id', $item['id'])->update([
                'display_order' => $item['display_order'],
                'order' => $item['display_order'],
            ]);
        }

        return ResponseHelper::success(null, 'Cập nhật thứ tự banner thành công');
    }

    /**
     * Xóa banner
     * DELETE /api/admin/banners/{banner}
     */
    public function destroy(Banner $banner)
    {
        $banner->delete();
        return ResponseHelper::success(null, 'Đã xóa banner');
    }
}
