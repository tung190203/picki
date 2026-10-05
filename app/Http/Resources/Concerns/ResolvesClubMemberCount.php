<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Helper dùng chung cho các ClubResource để tính `quantity_members`.
 *
 * Quy ước số thành viên của CLB = user thật (đã join + active) + thành viên ảo
 * (club_virtual_members). Trước đây chỉ đếm user thật nên số liệu thấp hơn thực tế.
 *
 * ⚠️ Lưu ý quan trọng về tên attribute:
 *  `withCount('activeMembers')` của Laravel sinh attribute tên `active_members_count`
 *  (snake_case tự động) — CHƯA bao gồm thành viên ảo.
 *  Trong khi `ClubDetailAssembler` cũng set `active_members_count` nhưng ĐÃ cộng sẵn
 *  thành viên ảo vào, đồng thời đánh dấu bằng cờ `_virtual_members_counted`.
 *
 *  Vì vậy: chỉ coi `active_members_count` là "đã bao gồm thành viên ảo" khi cờ
 *  `_virtual_members_counted` = true. Nếu không có cờ thì đây là raw count của
 *  `withCount('activeMembers')` và vẫn phải cộng thêm thành viên ảo.
 *
 * @mixin \Illuminate\Http\Resources\Json\JsonResource
 */
trait ResolvesClubMemberCount
{
    /**
     * Cờ đánh dấu: giá trị `active_members_count` đã bao gồm thành viên ảo.
     * ClubDetailAssembler set cờ này sau khi cộng.
     */
    public const FLAG_VIRTUAL_MEMBERS_COUNTED = '_virtual_members_counted';

    /**
     * Tổng số thành viên CLB = user thật (joined + active) + thành viên ảo.
     */
    protected function resolveClubQuantityMembers(): int
    {
        $model = $this->resource;

        if (!$model instanceof Model) {
            return 0;
        }

        // Đã cộng sẵn cả user thật + thành viên ảo (ClubDetailAssembler) → dùng luôn.
        if ($model->getAttribute(self::FLAG_VIRTUAL_MEMBERS_COUNTED) === true
            && $model->getAttribute('active_members_count') !== null) {
            return (int) $model->getAttribute('active_members_count');
        }

        $realCount = $this->pickCount($model, [
            'active_members_count',   // withCount('activeMembers') — Laravel snake_case hoá
            'activeMembers_count',     // withCount(['activeMembers as activeMembers_count'])
            'members_count',           // withCount('members')
        ], ['activeMembers', 'members']);

        $virtualCount = $this->pickCount($model, [
            'virtual_members_count',
        ], ['virtualMembers']);

        // Thiếu phần nào thì chỉ query phần còn thiếu (tránh query thừa).
        $realCount ??= $model->members()->count();
        $virtualCount ??= $model->virtualMembers()->count();

        return (int) $realCount + (int) $virtualCount;
    }

    /**
     * Lấy count đầu tiên có sẵn: ưu tiên attribute từ withCount, sau đó tới
     * relation đã load. Trả về null nếu không có nguồn nào.
     *
     * @param  array<int, string>  $countAttributes
     * @param  array<int, string>  $relations
     */
    protected function pickCount(Model $model, array $countAttributes, array $relations): ?int
    {
        foreach ($countAttributes as $attribute) {
            $value = $model->getAttribute($attribute);
            if ($value !== null) {
                return (int) $value;
            }
        }

        foreach ($relations as $relation) {
            if ($model->relationLoaded($relation)) {
                return $model->getRelation($relation)->count();
            }
        }

        return null;
    }
}
