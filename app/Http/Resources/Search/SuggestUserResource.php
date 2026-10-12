<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource cho User Suggest - extend SearchPlayerResource.
 * Thêm 3 fields: category, category_text, relation.
 */
class SuggestUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Render base fields từ SearchPlayerResource
        $card = (new SearchPlayerResource($this->resource))->resolve($request);

        // Thêm fields mới
        $card['category'] = $this->resource->category ?? null;
        $card['category_text'] = $this->resource->category_text ?? null;
        $card['relation'] = $this->buildRelation();

        return $card;
    }

    /**
     * Build relation object từ preloaded_relation.
     */
    private function buildRelation(): ?array
    {
        $relation = $this->resource->preloaded_relation ?? null;

        if (!$relation) {
            return null;
        }

        return [
            'matches_together' => (int) ($relation->matches_together ?? 0),
            'mini_tournaments_together' => (int) ($relation->mini_tournaments_together ?? 0),
            'tournaments_together' => (int) ($relation->tournaments_together ?? 0),
            'clubs_together' => (int) ($relation->clubs_together ?? 0),
        ];
    }
}
