<?php

namespace App\Http\Resources\Club;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ClubMemberUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        unset($data['clubs']);

        return $data;
    }
}
