<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoreVerificationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
