<?php

namespace App\Services;

use App\Models\MiniParticipant;
use App\Models\Participant;

class ParticipantScoreService
{
    public function modifyScore(Participant|MiniParticipant $participant, mixed $score): Participant|MiniParticipant
    {
        $participant->update(['modified_score' => $score !== null ? (float) $score : null]);

        return $participant->fresh();
    }

    public function getEffectiveScore(Participant|MiniParticipant $participant): ?float
    {
        if ($participant->modified_score !== null) {
            return (float) $participant->modified_score;
        }

        $user = $participant->user;
        if (!$user) {
            return null;
        }

        $vnduprScore = $user->vnduprScores()->max('score_value');

        return $vnduprScore !== null ? (float) $vnduprScore : null;
    }
}
