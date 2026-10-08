<?php

namespace App\Http\Resources\Search;

use App\Http\Resources\Concerns\ResolvesClubMemberCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phase 3: Card cho Club Suggest.
 * Reuse SearchClubResource (Phase 1) để có đầy đủ card fields,
 * chỉ thêm 2 fields mới: category + category_text.
 */
class SuggestClubResource extends JsonResource
{
    use ResolvesClubMemberCount;

    public function toArray(Request $request): array
    {
        // Render full Phase 1 card từ SearchClubResource
        $card = (new SearchClubResource($this->resource))->resolve($request);

        // Chỉ thêm 2 fields mới
        $card['category'] = $this->resource->category ?? null;
        $card['category_text'] = $this->resource->category_text ?? null;

        return $card;
    }
}