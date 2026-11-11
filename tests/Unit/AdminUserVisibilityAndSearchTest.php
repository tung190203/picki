<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminUserVisibilityAndSearchTest extends TestCase
{
    /**
     * Kiểm tra UserManagementService có select field 'visibility'
     */
    public function test_user_management_service_selects_visibility(): void
    {
        $file = file_get_contents(dirname(__DIR__, 2) . '/app/Services/Admin/UserManagementService.php');
        $this->assertStringContainsString("'visibility'", $file, 'UserManagementService::search phải select cột visibility');
    }

    /**
     * Kiểm tra SearchV2Controller TAB_USER không còn lọc visibleFor
     */
    public function test_search_v2_controller_does_not_filter_visible_for(): void
    {
        $file = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/SearchV2Controller.php');
        
        // Đoạn TAB_USER không được chứa visibleFor
        $pattern = '/TAB_USER\s*=>\s*User::query\(\).*?->filter\(\$filters\)/s';
        if (preg_match($pattern, $file, $matches)) {
            $this->assertStringNotContainsString('visibleFor', $matches[0], 'TAB_USER không được chứa visibleFor');
        } else {
            $this->assertStringNotContainsString('->visibleFor', $file, 'SearchV2Controller không được chứa ->visibleFor');
        }
    }

    /**
     * Kiểm tra UserObserver có method created khởi tạo Pickleball sport_id = 1 và vndupr_score = 1.0
     */
    public function test_user_observer_has_created_hook_for_default_sport(): void
    {
        $file = file_get_contents(dirname(__DIR__, 2) . '/app/Observers/UserObserver.php');
        $this->assertStringContainsString('public function created(User $user)', $file, 'UserObserver phải có method created');
        $this->assertStringContainsString("'sport_id' => 1", $file, 'UserObserver::created phải khởi tạo Pickleball sport_id = 1');
        $this->assertStringContainsString("'vndupr_score'", $file, 'UserObserver::created phải khởi tạo vndupr_score');
        $this->assertStringContainsString('1.0', $file, 'UserObserver::created phải đặt điểm sàn 1.0');
    }
}
