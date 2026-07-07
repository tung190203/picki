<?php

namespace App\Exceptions;

use App\Models\Matches;
use Exception;

class VersionConflictException extends Exception
{
    private ?Matches $match;

    public function __construct(?Matches $match = null)
    {
        $message = 'Version conflict: the match state has been modified by another request.';
        parent::__construct($message, 409);
        $this->match = $match;
    }

    public function getMatch(): ?Matches
    {
        return $this->match;
    }
}
