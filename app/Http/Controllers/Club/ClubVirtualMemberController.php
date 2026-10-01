<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Models\Club\ClubVirtualMember;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;

class ClubVirtualMemberController extends Controller
{
    protected $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        $query = $club->virtualMembers();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $virtualMembers = $query->orderBy('name', 'asc')->get();

        return ResponseHelper::success($virtualMembers, 'Lấy danh sách thành viên ảo thành công');
    }

    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        if (!$club->canManage(auth()->id())) {
            return ResponseHelper::error('Chỉ ban quản trị CLB mới có quyền tạo thành viên ảo', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'avatar' => 'nullable|image|max:2048',
            'notes' => 'nullable|string|max:500',
        ], [
            'name.required' => 'Vui lòng nhập tên thành viên',
            'avatar.image' => 'Ảnh đại diện phải là một tệp hình ảnh',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 2MB',
        ]);

        // Ưu tiên file upload; nếu không có thì nhận URL string cũ (back-compat).
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->imageService->optimize(
                $validated['avatar'],
                'club_virtual_member_avatars',
                800,
                85
            );
        } else {
            $avatarUrl = $request->input('avatar_url');
            if (is_string($avatarUrl) && $avatarUrl !== '') {
                $avatarPath = $avatarUrl;
            }
        }

        $virtualMember = ClubVirtualMember::create([
            'club_id' => $club->id,
            'name' => trim($validated['name']),
            'avatar_url' => $avatarPath,
            'created_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return ResponseHelper::success($virtualMember, 'Thêm thành viên ảo thành công', 201);
    }

    public function destroy($clubId, $virtualMemberId)
    {
        $club = Club::findOrFail($clubId);

        if (!$club->canManage(auth()->id())) {
            return ResponseHelper::error('Chỉ ban quản trị CLB mới có quyền xóa thành viên ảo', 403);
        }

        $virtualMember = ClubVirtualMember::where('club_id', $club->id)->findOrFail($virtualMemberId);
        $virtualMember->delete(); // Soft delete

        return ResponseHelper::success(null, 'Xóa thành viên ảo thành công');
    }
}