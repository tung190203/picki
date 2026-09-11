<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\SponsorResource;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminSponsorController extends Controller
{
    /**
     * Danh sách sponsor cho Admin
     * GET /api/admin/sponsors
     */
    public function index(Request $request)
    {
        $query = Sponsor::with('creator');

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $sponsors = $query->orderBy('display_order', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        $stats = [
            'total' => Sponsor::count(),
            'active' => Sponsor::where('is_active', true)->count(),
            'inactive' => Sponsor::where('is_active', false)->count(),
            'with_link' => Sponsor::whereNotNull('website_url')->where('website_url', '!=', '')->count(),
        ];

        return ResponseHelper::success([
            'sponsors' => SponsorResource::collection($sponsors),
            'stats' => $stats,
        ]);
    }

    /**
     * Tạo mới sponsor
     * POST /api/admin/sponsors
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'logo' => 'nullable|file|mimes:jpeg,png,jpg,webp,svg,gif|max:5120',
            'logo_url' => 'nullable|string',
            'logo_dark' => 'nullable|file|mimes:jpeg,png,jpg,webp,svg,gif|max:5120',
            'logo_dark_url' => 'nullable|string',
            'website_url' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $logoPath = $validated['logo_url'] ?? null;
        $logoDarkPath = $validated['logo_dark_url'] ?? null;

        // Xử lý logo dương bản (nền sáng)
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $extension = $file->getClientOriginalExtension();
            $filename = 'sponsor_pos_' . time() . '_' . uniqid() . '.' . $extension;
            $path = $file->storeAs('sponsors', $filename, 'public');
            $logoPath = $path;
        }

        // Xử lý logo âm bản (nền tối / Dark Mode)
        if ($request->hasFile('logo_dark')) {
            $darkFile = $request->file('logo_dark');
            $darkExt = $darkFile->getClientOriginalExtension();
            $darkFilename = 'sponsor_neg_' . time() . '_' . uniqid() . '.' . $darkExt;
            $darkPath = $darkFile->storeAs('sponsors', $darkFilename, 'public');
            $logoDarkPath = $darkPath;
        }

        if (empty($logoPath)) {
            return ResponseHelper::error('Vui lòng tải lên ảnh logo nhãn hàng tài trợ (dương bản).', 422);
        }

        $nextOrder = $validated['display_order'] ?? ((Sponsor::max('display_order') ?? 0) + 1);

        $sponsor = Sponsor::create([
            'name' => $validated['name'] ?? null,
            'logo_url' => $logoPath,
            'logo_dark_url' => $logoDarkPath,
            'website_url' => $validated['website_url'] ?? null,
            'display_order' => $nextOrder,
            'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'created_by' => Auth::id(),
        ]);

        $sponsor->load('creator');

        return ResponseHelper::success(
            new SponsorResource($sponsor),
            'Thêm nhãn hàng tài trợ thành công',
            201
        );
    }

    /**
     * Chi tiết sponsor
     * GET /api/admin/sponsors/{sponsor}
     */
    public function show(Sponsor $sponsor)
    {
        $sponsor->load('creator');
        return ResponseHelper::success(new SponsorResource($sponsor));
    }

    /**
     * Cập nhật sponsor
     * POST /api/admin/sponsors/{sponsor} hoặc PUT
     */
    public function update(Request $request, Sponsor $sponsor)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'logo' => 'nullable|file|mimes:jpeg,png,jpg,webp,svg,gif|max:5120',
            'logo_url' => 'nullable|string',
            'logo_dark' => 'nullable|file|mimes:jpeg,png,jpg,webp,svg,gif|max:5120',
            'logo_dark_url' => 'nullable|string',
            'website_url' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data = [];

        if ($request->exists('name') || array_key_exists('name', $validated)) {
            $nameVal = $validated['name'] ?? null;
            $data['name'] = !empty(trim((string)$nameVal)) ? trim((string)$nameVal) : null;
        }

        if ($request->exists('website_url') || array_key_exists('website_url', $validated)) {
            $urlVal = $validated['website_url'] ?? null;
            $data['website_url'] = !empty(trim((string)$urlVal)) ? trim((string)$urlVal) : null;
        }

        if ($request->has('display_order')) {
            $data['display_order'] = (int) $validated['display_order'];
        }

        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        // Cập nhật logo dương bản (nền sáng)
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $extension = $file->getClientOriginalExtension();
            $filename = 'sponsor_pos_' . time() . '_' . uniqid() . '.' . $extension;
            $path = $file->storeAs('sponsors', $filename, 'public');

            // Xóa file cũ
            if (!empty($sponsor->logo_url) && !str_starts_with($sponsor->logo_url, 'http')) {
                Storage::disk('public')->delete($sponsor->logo_url);
            }

            $data['logo_url'] = $path;
        } elseif (!empty($validated['logo_url'])) {
            $data['logo_url'] = $validated['logo_url'];
        }

        // Cập nhật logo âm bản (nền tối)
        if ($request->hasFile('logo_dark')) {
            $darkFile = $request->file('logo_dark');
            $darkExt = $darkFile->getClientOriginalExtension();
            $darkFilename = 'sponsor_neg_' . time() . '_' . uniqid() . '.' . $darkExt;
            $darkPath = $darkFile->storeAs('sponsors', $darkFilename, 'public');

            // Xóa file âm bản cũ nếu có
            if (!empty($sponsor->logo_dark_url) && !str_starts_with($sponsor->logo_dark_url, 'http')) {
                Storage::disk('public')->delete($sponsor->logo_dark_url);
            }

            $data['logo_dark_url'] = $darkPath;
        } elseif ($request->exists('logo_dark_url')) {
            $darkVal = $validated['logo_dark_url'] ?? null;
            if (empty($darkVal)) {
                // Xóa ảnh âm bản cũ nếu admin muốn gỡ
                if (!empty($sponsor->logo_dark_url) && !str_starts_with($sponsor->logo_dark_url, 'http')) {
                    Storage::disk('public')->delete($sponsor->logo_dark_url);
                }
                $data['logo_dark_url'] = null;
            } else {
                $data['logo_dark_url'] = $darkVal;
            }
        }

        $sponsor->update($data);
        $sponsor->load('creator');

        return ResponseHelper::success(
            new SponsorResource($sponsor),
            'Cập nhật nhãn hàng tài trợ thành công'
        );
    }

    /**
     * Bật / tắt nhanh trạng thái
     * PATCH /api/admin/sponsors/{sponsor}/toggle-status
     */
    public function toggleActive(Sponsor $sponsor)
    {
        $sponsor->update(['is_active' => !$sponsor->is_active]);

        return ResponseHelper::success(
            new SponsorResource($sponsor),
            $sponsor->is_active ? 'Đã bật hiển thị nhãn hàng' : 'Đã ẩn nhãn hàng'
        );
    }

    /**
     * Cập nhật thứ tự hàng loạt
     * POST /api/admin/sponsors/reorder
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer|exists:sponsors,id',
            'orders.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $item) {
            Sponsor::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        return ResponseHelper::success(null, 'Cập nhật thứ tự thành công');
    }

    /**
     * Xóa sponsor
     * DELETE /api/admin/sponsors/{sponsor}
     */
    public function destroy(Sponsor $sponsor)
    {
        if (!empty($sponsor->logo_url) && !str_starts_with($sponsor->logo_url, 'http')) {
            Storage::disk('public')->delete($sponsor->logo_url);
        }

        if (!empty($sponsor->logo_dark_url) && !str_starts_with($sponsor->logo_dark_url, 'http')) {
            Storage::disk('public')->delete($sponsor->logo_dark_url);
        }

        $sponsor->delete();

        return ResponseHelper::success(null, 'Đã xóa nhãn hàng tài trợ thành công');
    }
}
