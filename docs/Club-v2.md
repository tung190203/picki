# Tài liệu API — Tính năng CLB guest & BXH Thành tích CLB (Sao & Cúp)

Tài liệu này tổng hợp các API liên quan đến **CLB guest** (thay thế hoàn toàn flow "Thành viên ảo" / `ClubVirtualMember` cũ từ 2026-10-07) và **BXH Thành tích (Sao & Cúp)**.

> **Quan trọng — di sản từ VM**: từ migration ngày 2026-10-07, mọi `ClubVirtualMember` cũ được convert sang `User` (`is_guest=true`) + `ClubGuestProfile`. CLB guest giờ là **User thật** có `user_id`, có thể được mời vào **MỌI** mini-tournament/Tournament (kể cả event của CLB khác, event cá nhân). Bảng `club_guest_profiles` chỉ là "ownership record" để CLB gốc quản lý danh sách guest của mình — KHÔNG ràng buộc scope mời.

> Xem chi tiết API mới ở [`docs/club-v4-api.md`](./club-v4-api.md) — phần "Phần 2.5 — CLB guests (User.is_guest + club_guest_profiles)".

---

## Danh sách Endpoints (cập nhật)

| STT | Phương thức | Endpoint | Mô tả |
| --- | --- | --- | --- |
| 1 | `GET` | `/api/clubs/{id}/guests/profiles` | Lấy danh sách CLB guest của CLB |
| 2 | `POST` | `/api/clubs/{id}/guests/profiles` | Tạo mới CLB guest trong CLB |
| 3 | `PUT` | `/api/clubs/{id}/guests/profiles/{id}` | Cập nhật CLB guest |
| 4 | `DELETE` | `/api/clubs/{id}/guests/profiles/{id}` | Xóa mềm CLB guest |
| 5 | `GET` | `/api/clubs/{id}/leaderboard` | Lấy Bảng xếp hạng CLB (Điểm trình / Thành tích Sao & Cúp) |
| 6 | `GET` | `/api/mini-tournaments/{id}/candidates` | Tìm kiếm ứng viên tham gia Kèo đấu (tích hợp CLB guest) |
| 7 | `GET` | `/api/tournaments/{id}/candidates` | Tìm kiếm ứng viên tham gia Giải đấu (tích hợp CLB guest) |

> **Đã xoá từ 2026-10-07** (thay bằng 4 endpoint ở trên):
> - `GET/POST/DELETE /api/clubs/{id}/virtual-members[...]`
> - Trường `virtual_member_id`, `is_virtual` trên các bảng `tournament_staff`, `mini_tournament_staff` (giờ chỉ còn `user_id` thật, kể cả `User.is_guest=true`).

---

## Tóm tắt thay đổi chính

1. **Bảng `club_virtual_members` đã được xoá**. Mọi VM cũ đã migrate sang `User` (`is_guest=true`) + `club_guest_profiles` trong 1 migration ngày 2026-10-07.
2. **Snapshot `user_id` đã được backfill** trên các bảng `participants`, `mini_participants`, `tournament_staff`, `mini_tournament_staff` (cũ) — match theo `guest_name`.
3. **Cột `is_virtual` và `virtual_member_id` đã xoá** khỏi `tournament_staff` và `mini_tournament_staff`.
4. **2 field mới trên `POST /api/mini-tournaments/{id}/guests` và `POST /api/tournaments/{id}/guests`**:
   - `club_guest_profile_id` (int, optional) — chọn CLB guest có sẵn để mời vào event.
   - `create_club_guest_for_club_id` (int, optional) — tạo CLB guest mới (User + ClubGuestProfile) + thêm vào event trong 1 transaction.
5. **`POST /api/mini-tournaments/{id}/participants/invite` và `POST /api/tournaments/{id}/participants/invite` (multi-invite)** cũng nhận 2 field mới:
   - `club_guest_profile_ids` (array)
   - `create_club_guest_for_club_id` (int)
6. **`quantity_members` của CLB** vẫn đếm cả user ảo: `quantity_members = club_members (joined + active) + club_guest_profiles`. Logic nằm trong `App\Http\Resources\Concerns\ResolvesClubMemberCount`.
7. **BXH Thành tích** (`/api/clubs/{id}/leaderboard?type=achievement`): response giờ dùng `user_id` (kể cả User.is_guest=true) thay cho `virtual_member_id`. Field `is_virtual` được thay bằng `is_guest`.
8. **Tìm kiếm ứng viên** (`/api/mini-tournaments/{id}/candidates` và `/api/tournaments/{id}/candidates` với `scope=club`): response giờ dùng `user_id` thay cho `virtual_member_id`. Field `is_virtual` được thay bằng `is_guest`.

Xem chi tiết từng API, payload, response và quy tắc ở [`docs/club-v4-api.md`](./club-v4-api.md).
