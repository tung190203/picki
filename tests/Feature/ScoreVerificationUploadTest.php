<?php

namespace Tests\Feature;

use App\Enums\ScoreType;
use App\Enums\ScoreVerificationStatus;
use App\Models\ScoreVerificationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScoreVerificationUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_when_user_has_any_pending_request(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        ScoreVerificationRequest::create([
            'user_id' => $user->id,
            'score_type' => ScoreType::SPCN->value,
            'submitted_score' => 2.0,
            'image_path' => 'score-verifications/existing.jpg',
            'status' => ScoreVerificationStatus::PENDING->value,
        ]);

        $payload = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($user)
            ->postJson('/api/score-verifications', [
                'score_type' => ScoreType::DUPR->value,
                'score' => 3.0,
                'image' => $payload,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score_type']);
    }

    public function test_store_accepts_when_user_has_no_pending_request(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        ScoreVerificationRequest::create([
            'user_id' => $user->id,
            'score_type' => ScoreType::SPCN->value,
            'submitted_score' => 2.0,
            'image_path' => 'score-verifications/old.jpg',
            'status' => ScoreVerificationStatus::REJECTED->value,
        ]);

        $payload = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($user)
            ->postJson('/api/score-verifications', [
                'score_type' => ScoreType::DUPR->value,
                'score' => 3.0,
                'image' => $payload,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('score_verification_requests', [
            'user_id' => $user->id,
            'score_type' => ScoreType::DUPR->value,
            'status' => ScoreVerificationStatus::PENDING->value,
        ]);
    }
}