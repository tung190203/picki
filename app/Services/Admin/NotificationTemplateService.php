<?php

namespace App\Services\Admin;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\RecipientType;
use App\Enums\AdminPushNotification\SendType;
use App\Http\Requests\Admin\NotificationTemplate\CreateTemplateRequest;
use App\Http\Requests\Admin\NotificationTemplate\UpdateTemplateRequest;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;

class NotificationTemplateService
{
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationTemplate::orderByDesc('id')->get();
    }

    public function getById(int $id): ?NotificationTemplate
    {
        return NotificationTemplate::find($id);
    }

    public function create(CreateTemplateRequest $request, int $adminId): NotificationTemplate
    {
        $validated = $request->validated();

        return NotificationTemplate::create([
            'created_by' => $adminId,
            'name' => $validated['name'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'action_type' => $validated['action_type'] ?? ActionType::NONE->value,
            'action_id' => $validated['action_id'] ?? null,
            'recipient_type' => $validated['recipient_type'],
            'recipient_config' => $validated['recipient_config'] ?? [],
        ]);
    }

    public function update(NotificationTemplate $template, UpdateTemplateRequest $request): NotificationTemplate
    {
        $validated = $request->validated();

        $template->update([
            'name' => $validated['name'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'action_type' => $validated['action_type'] ?? ActionType::NONE->value,
            'action_id' => $validated['action_id'] ?? null,
            'recipient_type' => $validated['recipient_type'],
            'recipient_config' => $validated['recipient_config'] ?? [],
        ]);

        return $template->fresh();
    }

    public function delete(NotificationTemplate $template): bool
    {
        return $template->delete();
    }
}
