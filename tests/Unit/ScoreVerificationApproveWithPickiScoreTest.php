<?php

namespace Tests\Unit;

use App\Http\Requests\ApproveScoreVerificationRequest;
use App\Http\Requests\UpdateScoreVerificationPickiScoreRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ScoreVerificationApproveWithPickiScoreTest extends TestCase
{
    /**
     * Test validation của ApproveScoreVerificationRequest với field picki_score
     */
    public function test_picki_score_validation_rules(): void
    {
        $request = new ApproveScoreVerificationRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('picki_score', $rules);
        $this->assertArrayHasKey('award_anchor_badge', $rules);

        // 1. Hợp lệ: Không truyền picki_score
        $v1 = Validator::make([], $rules);
        $this->assertFalse($v1->fails());

        // 2. Hợp lệ: Truyền picki_score là null
        $v2 = Validator::make(['picki_score' => null], $rules);
        $this->assertFalse($v2->fails());

        // 3. Hợp lệ: Truyền picki_score hợp lệ (ví dụ 3.5, 1.0, 10.0)
        $v3 = Validator::make(['picki_score' => 3.5, 'award_anchor_badge' => true], $rules);
        $this->assertFalse($v3->fails());

        $v4 = Validator::make(['picki_score' => '2.75'], $rules);
        $this->assertFalse($v4->fails());

        // 4. Không hợp lệ: picki_score < 1.0
        $v5 = Validator::make(['picki_score' => 0.8], $rules);
        $this->assertTrue($v5->fails());
        $this->assertArrayHasKey('picki_score', $v5->errors()->toArray());

        // 5. Không hợp lệ: picki_score > 10.0
        $v6 = Validator::make(['picki_score' => 10.5], $rules);
        $this->assertTrue($v6->fails());

        // 6. Không hợp lệ: picki_score là chuỗi không phải số
        $v7 = Validator::make(['picki_score' => 'not_a_number'], $rules);
        $this->assertTrue($v7->fails());
    }

    /**
     * Kiểm tra ScoreVerificationRequest model có fillable và cast cho picki_score
     */
    public function test_model_has_picki_score_fillable_and_cast(): void
    {
        $model = new \App\Models\ScoreVerificationRequest();
        $this->assertContains('picki_score', $model->getFillable());
        $this->assertEquals('decimal:3', $model->getCasts()['picki_score'] ?? null);
    }

    /**
     * Kiểm tra ScoreVerificationRepository có method hỗ trợ approved status
     */
    public function test_repository_methods_exist(): void
    {
        $file = file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/ScoreVerificationRepository.php');
        $this->assertStringContainsString('canBeApprovedOrUpdated', $file);
        $this->assertStringContainsString('getWithLockForApprove', $file);
        $this->assertStringContainsString('ScoreVerificationStatus::APPROVED->value', $file);
    }

    /**
     * Kiểm tra ScoreVerificationService::approveRequest nhận param pickiScore và xử lý case already approved
     */
    public function test_service_supports_already_approved_and_picki_score(): void
    {
        $file = file_get_contents(dirname(__DIR__, 2) . '/app/Services/ScoreVerificationService.php');
        $this->assertStringContainsString('canBeApprovedOrUpdated', $file);
        $this->assertStringContainsString('getWithLockForApprove', $file);
        $this->assertStringContainsString('?float $pickiScore = null', $file);
        $this->assertStringContainsString('$isAlreadyApproved', $file);
        $this->assertStringContainsString('vndupr_score', $file);
        $this->assertStringContainsString('public function updatePickiScore', $file);
    }

    /**
     * Test validation của UpdateScoreVerificationPickiScoreRequest
     */
    public function test_update_picki_score_request_validation(): void
    {
        $request = new UpdateScoreVerificationPickiScoreRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('picki_score', $rules);
        $this->assertContains('required', $rules['picki_score']);

        // 1. Thất bại nếu không truyền picki_score
        $v1 = Validator::make([], $rules);
        $this->assertTrue($v1->fails());

        // 2. Thành công nếu truyền picki_score hợp lệ
        $v2 = Validator::make(['picki_score' => 3.25, 'award_anchor_badge' => true], $rules);
        $this->assertFalse($v2->fails());

        // 3. Thất bại nếu picki_score < 1.0
        $v3 = Validator::make(['picki_score' => 0.5], $rules);
        $this->assertTrue($v3->fails());
    }

    /**
     * Kiểm tra Route update-picki-score đã được đăng ký
     */
    public function test_route_update_picki_score_is_registered(): void
    {
        $routes = Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'score-verifications/{verification}/update-picki-score')) {
                $hasRoute = true;
                break;
            }
        }
        $this->assertTrue($hasRoute, 'Route POST admin/score-verifications/{verification}/update-picki-score phải được đăng ký');
    }
}
