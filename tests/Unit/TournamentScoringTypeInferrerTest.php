<?php

namespace Tests\Unit;

use App\Services\TournamentScoringTypeInferrer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TournamentScoringTypeInferrerTest extends TestCase
{
    private TournamentScoringTypeInferrer $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new TournamentScoringTypeInferrer();
    }

    // -------------------------------------------------------------------------
    // inferScoringType
    // -------------------------------------------------------------------------

    public function test_infer_scoring_type_2_is_side_out(): void
    {
        $this->assertSame(TournamentScoringTypeInferrer::SIDE_OUT, $this->svc->inferScoringType(2));
    }

    public function test_infer_scoring_type_3_is_rally(): void
    {
        $this->assertSame(TournamentScoringTypeInferrer::RALLY, $this->svc->inferScoringType(3));
    }

    public function test_infer_scoring_type_4_is_rally(): void
    {
        $this->assertSame(TournamentScoringTypeInferrer::RALLY, $this->svc->inferScoringType(4));
    }

    // -------------------------------------------------------------------------
    // defaultStagePoints
    // -------------------------------------------------------------------------

    public function test_default_stage_points_default_count(): void
    {
        $this->assertSame([10, 20, 30], $this->svc->defaultStagePoints());
    }

    public function test_default_stage_points_count_2(): void
    {
        $this->assertSame([10, 20], $this->svc->defaultStagePoints(2));
    }

    public function test_default_stage_points_count_5(): void
    {
        $this->assertSame([10, 20, 30, 40, 50], $this->svc->defaultStagePoints(5));
    }

    // -------------------------------------------------------------------------
    // validateStagePoints — happy paths
    // -------------------------------------------------------------------------

    public function test_validate_stage_points_default(): void
    {
        $out = $this->svc->validateStagePoints([10, 20, 30]);
        $this->assertSame([10, 20, 30], $out);
    }

    public function test_validate_stage_points_with_large_gap(): void
    {
        $out = $this->svc->validateStagePoints([8, 16, 24]);
        $this->assertSame([8, 16, 24], $out);
    }

    public function test_validate_stage_points_count_2(): void
    {
        $out = $this->svc->validateStagePoints([10, 20]);
        $this->assertSame([10, 20], $out);
    }

    public function test_validate_stage_points_count_4(): void
    {
        $out = $this->svc->validateStagePoints([10, 20, 30, 40]);
        $this->assertSame([10, 20, 30, 40], $out);
    }

    public function test_validate_stage_points_count_5(): void
    {
        $out = $this->svc->validateStagePoints([10, 20, 30, 40, 50]);
        $this->assertSame([10, 20, 30, 40, 50], $out);
    }

    // -------------------------------------------------------------------------
    // validateStagePoints — error paths
    // -------------------------------------------------------------------------

    public function test_validate_stage_points_rejects_count_below_min(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([10]);
    }

    public function test_validate_stage_points_rejects_count_above_max(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([10, 20, 30, 40, 50, 60]);
    }

    public function test_validate_stage_points_rejects_gap_too_small(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([8, 16, 20]);
    }

    public function test_validate_stage_points_rejects_not_strictly_increasing(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([12, 10, 30]);
    }

    public function test_validate_stage_points_rejects_equal_consecutive(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([10, 10, 30]);
    }

    public function test_validate_stage_points_rejects_non_integer(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([10, 20.5, 30]);
    }

    public function test_validate_stage_points_rejects_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->validateStagePoints([0, 10, 20]);
    }
}
