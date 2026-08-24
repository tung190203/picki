<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\RecipientType;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationTemplate\CreateTemplateRequest;
use App\Http\Requests\Admin\NotificationTemplate\UpdateTemplateRequest;
use App\Http\Resources\Admin\NotificationTemplate\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\Admin\NotificationTemplateService;
use Illuminate\Http\JsonResponse;

class NotificationTemplateController extends Controller
{
    public function __construct(
        protected NotificationTemplateService $service
    ) {}

    public function index(): JsonResponse
    {
        $templates = $this->service->getAll();

        return ResponseHelper::success([
            'templates' => NotificationTemplateResource::collection($templates),
        ], 'Lấy danh sách mẫu thông báo thành công');
    }

    public function store(CreateTemplateRequest $request): JsonResponse
    {
        $admin = $request->user();
        $template = $this->service->create($request, $admin->id);

        return ResponseHelper::success(
            new NotificationTemplateResource($template),
            'Lưu mẫu thông báo thành công',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $template = $this->service->getById($id);

        if (!$template) {
            return ResponseHelper::error('Mẫu thông báo không tồn tại', 404);
        }

        return ResponseHelper::success(
            new NotificationTemplateResource($template)
        );
    }

    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        $template = $this->service->getById($id);

        if (!$template) {
            return ResponseHelper::error('Mẫu thông báo không tồn tại', 404);
        }

        $updated = $this->service->update($template, $request);

        return ResponseHelper::success(
            new NotificationTemplateResource($updated),
            'Cập nhật mẫu thông báo thành công'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $template = $this->service->getById($id);

        if (!$template) {
            return ResponseHelper::error('Mẫu thông báo không tồn tại', 404);
        }

        $this->service->delete($template);

        return ResponseHelper::success(null, 'Xoá mẫu thông báo thành công');
    }
}
