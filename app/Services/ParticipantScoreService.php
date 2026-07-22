<?php

namespace App\Services;

use App\Models\MiniParticipant;
use App\Models\Participant;

class ParticipantScoreService
{
    public function modifyScore(Participant|MiniParticipant $participant, ?float $score): Participant|MiniParticipant
    {
        if ($score === null) {
            $participant->update(['modified_score' => null]);
        } else {
            $participant->update(['modified_score' => $score]);
        }

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
